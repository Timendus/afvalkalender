<?php

class iCal {

  function __construct($organisation, $calendarname) {
    $this->organisation = $organisation;
    $this->calendarname = $calendarname;
  }

  function render($events) {
    $ical  = $this->header();
    $ical .= $this->body($events);
    $ical .= $this->footer();
    return $ical;
  }

  function body($events) {
    $return = "";
    foreach ( $events as $event ) {
      $uid       = md5($event['date']->format('Ymd') . $event['summary']);
      $timestamp = $event['date']->format('Ymd\THis');
      $startdate = $event['date']->format('Ymd');
      $enddate   = clone $event['date'];
      $enddate   = $enddate->modify('+1 day')->format('Ymd');
      $return   .= $this->event($uid, $timestamp, $startdate, $enddate, $event['summary']);
    }
    return $return;
  }

  function header() {
    return <<<EOT
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//{$this->organisation}/{$this->calendarname}//NONSGML v1.0//EN
X-WR-CALNAME:Afvalkalender Twentemilieu
X-WR-TIMEZONE:Europe/Amsterdam
X-WR-CALDESC:Kalender feed van Timendus

EOT;
  }

  function event($uid, $timestamp, $startdate, $enddate, $summary) {
    return <<<EOT
BEGIN:VEVENT
UID:$uid
DTSTAMP:$timestamp
DTSTART;VALUE=DATE:$startdate
DTEND;VALUE=DATE:$enddate
SUMMARY:$summary
END:VEVENT

EOT;
  }

  function footer() {
    return <<<EOT
END:VCALENDAR
EOT;
  }

}

?>