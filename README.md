[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/T6T0DOOWP)

# Afvalkalender

_scratch your own itch_

## Intro

Ik weet niet hoe dat in andere regio's zit, maar Twente Milieu heeft de iCal download van haar website verwijderd
ten faveure van haar mobiele app. De argumentatie is dat men de ophaaldata, eenmaal in de kalenders van mensen, niet
meer kan aanpassen. Uiteraard had men dan niet een download moeten aanbieden, maar een feed. Problem solved.

Gek genoeg is er wel nog PDF download beschikbaar, die ook niet meer aan te passen valt na download..? Het voelt voor
mij toch een beetje alsof men ons vooral richting de mobiele app wil pushen. Misschien voor de PR, misschien voor het
verzamelen van nauwkeurigere statistieken over gebruikers... wie zal het zeggen.

Anyway, we hebben nu dus nog drie opties:

- De mobiele app van Twente Milieu installeren
- Een PDF downloaden en op een dode boom printen voor op de koelkast
- Elke dag naar de website gaan

Ik heb gekozen voor optie nummer drie. Maar dan wel geautomatiseerd. En met een iCal feed ;)

## Features

- Genereer een URL om aan je digitale agenda toe te voegen (dus geen download)
- De kalender wordt dagelijks ververst, en daarna gecached zodat we Twente Milieu niet platspammen
- Wijzigingen en verwijderingen in de agenda worden netjes overgenomen dmv `UIDs` zonder menselijke interventie
- Ophaaldata worden ~drie maanden vooruit getoond
- Stand-alone Python-code, uitsluitend met de standard library
- Een NixOS-module voor een kleine, afgeschermde systemd-service

## AI waarschuwing

Ik heb dit project in 2016 geschreven in PHP. De oorspronkelijke
PHP-implementatie is nog beschikbaar op de
[`php-legacy`](https://github.com/Timendus/afvalkalender/tree/php-legacy)
branch. De versie in `master` is een conversie naar Python3, gedaan door Codex,
omdat ik af wilde van de laatste stukjes PHP op mijn server. Ik heb de code
gereviewed en getest, maar het blijft een AI feestje. Als dat niet je ding is
dan moet je deze service niet draaien, of de oude PHP versie gebruiken.

## Installatie

### Lokaal draaien

```bash
python3 afvalkalender.py
```

De service luistert standaard op `127.0.0.1:8091`.

Gebruik `--host`, `--port` en `--cache-dir` om dat lokaal aan te passen:

```bash
python3 afvalkalender.py \
  --host 0.0.0.0 \
  --port 8080 \
  --cache-dir ./cache
```

De cache wordt per postcode en huisnummer bijgehouden en dagelijks ververst.

### NixOS

Hieronder staat een volledige module die je bijvoorbeeld vanuit je
`configuration.nix` kunt importeren. Pas het lokale pad aan als je de
repository ergens anders hebt uitgecheckt.

```nix
{ ... }:

{
  imports = [
    /where/ever/afvalkalender/nixos-module.nix
  ];

  services.afvalkalender = {
    enable = true;
    listenAddress = "127.0.0.1";
    port = 8091;
  };
};
```

De module draait de service met een tijdelijke systemd-gebruiker, een
schrijfbare state directory voor de dagelijkse cache en een beperkt
bestandssysteem. Er worden geen Python dependencies gebruikt behalve de standard
library.

## Contact

### Feedback / lof / kritiek?

mail@timendus.com

### Verbeteringen?

Ik zie de pull request wel verschijnen ;)
