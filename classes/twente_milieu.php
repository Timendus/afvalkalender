<?php

class TwenteMilieu {

  const cache_time_in_seconds = 60 * 60 * 24; // seconds x minutes x hours
  const cache_location        = './cache/';
  const user_agent            = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36';

  private $cookies;
  private $logged_in;

  public function __construct($unsafe_postcode, $unsafe_huisnummer) {
    $this->postcode   = $this->safePostcode($unsafe_postcode);
    $this->huisnummer = $this->safeHuisnummer($unsafe_huisnummer);

    $this->cookies    = tempnam('/tmp','cookie');
    $this->logged_in  = false;
  }

  public function safePostcode($unsafe_postcode = null) {
    if ( $unsafe_postcode == null ) {
      return $this->postcode;
    }

    $unsafe_postcode = preg_replace('/\s+/', '', $unsafe_postcode);
    if ( preg_match("/^[0-9]{4}[A-Z]{2}$/i", $unsafe_postcode) ) {
      return $unsafe_postcode;
    } else {
      throw new Exception("Ongeldige postcode");
    }
  }

  public function safeHuisnummer($unsafe_huisnummer = null) {
    if ( $unsafe_huisnummer == null ) {
      return $this->huisnummer;
    }

    $unsafe_huisnummer = filter_var($unsafe_huisnummer, FILTER_SANITIZE_NUMBER_INT);
    if ( preg_match("/^[0-9]+$/i", $unsafe_huisnummer) ) {
      return $unsafe_huisnummer;
    } else {
      throw new Exception("Ongeldig huisnummer");
    }
  }

  public function getEvents($from_date) {
    // Do requests for the coming three months
    $events = array();
    for( $i = 0; $i < 1; $i++ ) {
      $from_date->setDate($from_date->format('Y'), $from_date->format('m') + $i, 1);
      $events = array_merge(
        $events,
        $this->parsePage($this->requestPage($from_date), $from_date)
      );
    }

    return $events;
  }

  private function login() {
    $c = curl_init('https://afvalkalender.twentemilieu.nl/Afvalkalender/login.php');
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_COOKIEJAR, $this->cookies);
    curl_setopt($c, CURLOPT_VERBOSE, 1);
    curl_setopt($c, CURLOPT_USERAGENT, self::user_agent);
    curl_setopt($c, CURLOPT_REFERER, 'https://afvalkalender.twentemilieu.nl/');
    curl_setopt($c, CURLOPT_POSTFIELDS, 'postcode='.$this->safePostcode().'&huisnummer='.$this->safeHuisnummer());
    curl_exec($c);
    curl_close($c);
    // Postcode and huisnummer are now in the session
    $this->logged_in = true;
  }

  private function requestPage($request_date) {

    $cache_filename  = self::cache_location;
    $cache_filename .= $this->safePostcode() . $this->safeHuisnummer();
    $cache_filename .= $request_date->format('U') . '.html';

    $filemtime = @filemtime($cache_filename);

    if (!$filemtime or (time() - $filemtime >= self::cache_time_in_seconds)) {

      if ( !$this->logged_in ) {
        $this->login();
      }

      $c = curl_init('https://afvalkalender.twentemilieu.nl/maand?m='.$request_date->format('U'));
      curl_setopt($c, CURLOPT_VERBOSE, 1);
      curl_setopt($c, CURLOPT_COOKIEFILE, $this->cookies);
      curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($c, CURLOPT_USERAGENT, self::user_agent);
      curl_setopt($c, CURLOPT_REFERER, 'https://afvalkalender.twentemilieu.nl/');
      $result = curl_exec($c);
      curl_close($c);

      if ( file_put_contents($cache_filename, $result) === false ) {
        throw new Exception("Zorg ervoor dat de cache schrijfbaar is!");
      }

    } else {
      $result = file_get_contents($cache_filename);
    }

    return $result;
  }

  private function parsePage($page, $request_date) {
    $DOM = new DOMDocument;
    $DOM->loadHTML($page);
    $xpath = new DOMXpath($DOM);

    // Find all images in .kalendar
    $elements = $xpath->query("//*[contains(concat(' ', @class, ' '), ' kalender ')]//img");

    $events = array();
    foreach( $elements as $image ) {
      // Is it in this month?
      if ( !in_array('inactive', explode(' ',$image->parentNode->getAttribute('class')) ) ) {
        // If so, add it to the events array
        $day  = $image->parentNode->firstChild->nodeValue;
        $day  = filter_var($day, FILTER_SANITIZE_NUMBER_INT);
        $date = new DateTime();
        $date->setDate($request_date->format('Y'), $request_date->format('m'), $day);
        $date->setTime(0,0,0);
        $date->setTimezone(new DateTimeZone('Europe/Amsterdam'));

        $events[] = array(
          'date'    => $date,
          'summary' => $image->getAttribute('alt')
        );
      }
    }

    return $events;
  }

}

?>