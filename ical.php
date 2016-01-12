<?php

// header('Content-Type: text/calendar');
header('Content-Type: text/plain');

try {

  require 'classes/twente_milieu.php';
  require 'classes/ical.php';

  $afvalkalender  = new TwenteMilieu($_GET['postcode'], $_GET['huisnummer']);
  $ical_generator = new iCal('TwenteMilieu', 'Afvalkalender');

  // Find first of this month
  $request_date = new DateTime();
  $request_date->setDate($request_date->format('Y'), $request_date->format('m'), 1);
  $request_date->setTime(0,0,0);
  $request_date->setTimezone(new DateTimeZone('Europe/Amsterdam'));

  $events = $afvalkalender->getEvents($request_date);
  echo $ical_generator->render($events);

} catch ( Exception $e ) {

	echo "ERROR: {$e->getMessage()}";

}

?>