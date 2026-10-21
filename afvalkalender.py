#!/usr/bin/env python3
"""A small HTTP service that turns Twente Milieu pickup data into iCalendar."""

from __future__ import annotations

import argparse
import hashlib
import html
import json
import re
import sys
from datetime import date, datetime
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, urlencode, urlparse
from urllib.request import Request, urlopen


class ValidationError(ValueError):
    pass


class TwenteMilieu:
    api_domain = "https://twentemilieuapi.ximmio.com/api"
    company_code = "8d97bb56-5afd-4cbc-a651-b4f7314264b4"
    user_agent = (
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36"
    )
    pickup_types = {
        "GREY": "Restafval wordt opgehaald",
        "GREEN": "GFT wordt opgehaald",
        "PAPER": "Papier wordt opgehaald",
        "PACKAGES": "Verpakkingen worden opgehaald",
        "TREE": "Kerstbomen worden opgehaald",
    }
    postcode_re = re.compile(r"^[0-9]{4}[A-Z]{2}$")
    house_number_re = re.compile(r"^[0-9]+$")

    def __init__(self, postcode: str | None, house_number: str | None):
        self.postcode, self.house_number = self.validate_address(postcode, house_number)

    @classmethod
    def validate_address(cls, postcode: str | None, house_number: str | None) -> tuple[str, str]:
        normalized_postcode = re.sub(r"\s+", "", postcode or "").upper()
        if not cls.postcode_re.fullmatch(normalized_postcode):
            raise ValidationError("Ongeldige postcode")

        normalized_house_number = house_number or ""
        if not cls.house_number_re.fullmatch(normalized_house_number):
            raise ValidationError("Ongeldig huisnummer")

        return normalized_postcode, normalized_house_number

    def get_events(self) -> list[tuple[date, str]]:
        address = self.post_json(
            "FetchAdress",
            {
                "companyCode": self.company_code,
                "postCode": self.postcode,
                "houseNumber": self.house_number,
                "houseLetter": "",
            },
        )
        try:
            unique_id = address["dataList"][0]["UniqueId"]
        except (IndexError, KeyError, TypeError) as error:
            raise ValidationError("Adres niet gevonden") from error

        today = date.today()
        first_of_month = today.replace(day=1)
        end_month = first_of_month.month + 3
        end_year = first_of_month.year + (end_month - 1) // 12
        end_date = date(end_year, (end_month - 1) % 12 + 1, 1)
        calendar = self.post_json(
            "GetCalendar",
            {
                "companyCode": self.company_code,
                "uniqueAddressID": str(unique_id),
                "startDate": first_of_month.isoformat(),
                "endDate": end_date.isoformat(),
            },
        )

        events = []
        for pickup_type in calendar.get("dataList", []):
            summary = self.pickup_types.get(
                pickup_type.get("_pickupTypeText"),
                f"Onbekend afvaltype wordt opgehaald: '{pickup_type.get('_pickupTypeText', '')}'",
            )
            for pickup_date in pickup_type.get("pickupDates", []):
                events.append((datetime.fromisoformat(pickup_date.replace("Z", "+00:00")).date(), summary))
        return sorted(events)

    def post_json(self, endpoint: str, form: dict[str, str]) -> dict:
        request = Request(
            f"{self.api_domain}/{endpoint}",
            data=urlencode(form).encode(),
            headers={
                "User-Agent": self.user_agent,
                "Referer": "https://www.twentemilieu.nl/enschede/afval/afvalkalender",
                "Content-Type": "application/x-www-form-urlencoded",
            },
            method="POST",
        )
        try:
            with urlopen(request, timeout=10) as response:
                return json.load(response)
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as error:
            raise RuntimeError("Twente Milieu is momenteel niet bereikbaar") from error


def cached_events(cache_dir: Path, twente_milieu: TwenteMilieu) -> list[tuple[date, str]]:
    cache_key = hashlib.sha256(f"{twente_milieu.postcode}:{twente_milieu.house_number}".encode()).hexdigest()
    cache_file = cache_dir / f"{cache_key}.json"
    try:
        cached = json.loads(cache_file.read_text())
        if cached["date"] == date.today().isoformat():
            return [(date.fromisoformat(event_date), summary) for event_date, summary in cached["events"]]
    except (FileNotFoundError, KeyError, TypeError, ValueError, json.JSONDecodeError):
        pass

    events = twente_milieu.get_events()
    cache_dir.mkdir(parents=True, exist_ok=True)
    cache_file.write_text(
        json.dumps(
            {
                "date": date.today().isoformat(),
                "events": [(event_date.isoformat(), summary) for event_date, summary in events],
            }
        )
    )
    return events


class Ical:
    def render(self, events: list[tuple[date, str]]) -> str:
        timestamp = datetime.now().strftime("%Y%m%dT%H%M%S")
        lines = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:-//TwenteMilieu/Afvalkalender//NONSGML v1.0//EN",
            "X-WR-CALNAME:Afvalkalender Twentemilieu",
            "X-WR-TIMEZONE:Europe/Amsterdam",
            "X-WR-CALDESC:Kalender feed van Timendus",
        ]
        for pickup_date, summary in events:
            uid = hashlib.md5(f"{pickup_date:%Y%m%d}{summary}".encode()).hexdigest()
            lines.extend(
                [
                    "BEGIN:VEVENT",
                    f"UID:{uid}",
                    f"DTSTAMP:{timestamp}",
                    f"DTSTART;VALUE=DATE:{pickup_date:%Y%m%d}",
                    f"DTEND;VALUE=DATE:{pickup_date.fromordinal(pickup_date.toordinal() + 1):%Y%m%d}",
                    f"SUMMARY:{self.escape(summary)}",
                    "END:VEVENT",
                ]
            )
        return "\r\n".join(lines) + "\r\nEND:VCALENDAR\r\n"

    @staticmethod
    def escape(value: str) -> str:
        return value.replace("\\", "\\\\").replace(";", "\\;").replace(",", "\\,").replace("\n", "\\n")


class Handler(BaseHTTPRequestHandler):
    server_version = "Afvalkalender"

    def do_GET(self) -> None:  # noqa: N802
        request = urlparse(self.path)
        if request.path in ("", "/"):
            self.render_form(parse_qs(request.query))
        elif request.path == "/ical":
            self.render_calendar(parse_qs(request.query))
        else:
            self.send_error(HTTPStatus.NOT_FOUND)

    def render_form(self, query: dict[str, list[str]]) -> None:
        postcode = query.get("postcode", [""])[0]
        house_number = query.get("huisnummer", [""])[0]
        if not (postcode or house_number):
            self.respond_html(self.form())
            return
        try:
            twente_milieu = TwenteMilieu(postcode, house_number)
        except ValidationError as error:
            self.respond_html(self.form(str(error)), HTTPStatus.BAD_REQUEST)
            return
        url = f"ical?{urlencode({'postcode': twente_milieu.postcode, 'huisnummer': twente_milieu.house_number})}"
        self.respond_html(
            f"<p>Voeg deze url toe aan je agenda:</p><p><a href=\"{html.escape(url)}\">{html.escape(url)}</a></p>"
        )

    def render_calendar(self, query: dict[str, list[str]]) -> None:
        try:
            twente_milieu = TwenteMilieu(
                query.get("postcode", [None])[0], query.get("huisnummer", [None])[0]
            )
            calendar = Ical().render(cached_events(self.server.cache_dir, twente_milieu))
        except ValidationError as error:
            self.respond_text(str(error), HTTPStatus.BAD_REQUEST)
            return
        except RuntimeError as error:
            self.respond_text(str(error), HTTPStatus.BAD_GATEWAY)
            return
        self.respond_text(calendar, content_type="text/calendar; charset=utf-8")

    def form(self, error: str = "") -> str:
        error_html = f"<p>{html.escape(error)}</p>" if error else ""
        return f"""<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><title>Twente Milieu iCal</title>
<style>form, main {{ margin: 20px auto; width: 250px; font: 14px Tahoma; }} form input {{ float: right; }}</style>
</head><body><main>{error_html}<form method="get" action="">
<p>Postcode: <input type="text" name="postcode" placeholder="1234AB"></p>
<p>Huisnummer: <input type="text" name="huisnummer" placeholder="123"></p>
<input type="submit" value="Maak iCal link!"></form></main></body></html>"""

    def respond_html(self, body: str, status: HTTPStatus = HTTPStatus.OK) -> None:
        self.respond_text(body, status, "text/html; charset=utf-8")

    def respond_text(
        self, body: str, status: HTTPStatus = HTTPStatus.OK, content_type: str = "text/plain; charset=utf-8"
    ) -> None:
        encoded = body.encode()
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def log_message(self, format: str, *args: object) -> None:
        print(format % args, file=sys.stderr)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8091)
    parser.add_argument("--cache-dir", type=Path, default=Path("./cache"))
    args = parser.parse_args()
    server = ThreadingHTTPServer((args.host, args.port), Handler)
    server.cache_dir = args.cache_dir
    server.serve_forever()


if __name__ == "__main__":
    main()
