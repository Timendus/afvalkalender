<?php

class TwenteMilieu {

  const cache_time_in_seconds = 86400; // 60 seconds x 60 minutes x 24 hours
  const cache_location        = './cache/';
  const user_agent            = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36';
  const company_code          = '8d97bb56-5afd-4cbc-a651-b4f7314264b4';
  const api_domain            = 'https://twentemilieuapi.ximmio.com/api';

  public function __construct($unsafe_postcode, $unsafe_huisnummer) {
    $this->postcode   = $this->safePostcode($unsafe_postcode);
    $this->huisnummer = $this->safeHuisnummer($unsafe_huisnummer);
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
    $to_date = new DateTime();
    $to_date->setDate($from_date->format('Y'), $from_date->format('m') + 3, 1);
    return $this->getCalendar($from_date, $to_date);
  }

  private function getAddressUniqueId() {
    $c = curl_init(self::api_domain.'/FetchAdress');
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_VERBOSE, 1);
    curl_setopt($c, CURLOPT_USERAGENT, self::user_agent);
    curl_setopt($c, CURLOPT_REFERER, 'https://www.twentemilieu.nl/enschede');
    curl_setopt($c, CURLOPT_POSTFIELDS, 'companyCode='.self::company_code.'&postCode='.$this->safePostcode().'&houseNumber='.$this->safeHuisnummer());
    $result = curl_exec($c);
    curl_close($c);

    $json = json_decode($result);
    return $json->dataList[0]->UniqueId;
  }

  private function getCalendar($from_date, $to_date) {
    $c = curl_init(self::api_domain.'/GetCalendar');
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_VERBOSE, 1);
    curl_setopt($c, CURLOPT_USERAGENT, self::user_agent);
    curl_setopt($c, CURLOPT_REFERER, 'https://www.twentemilieu.nl/enschede');
    curl_setopt($c, CURLOPT_POSTFIELDS, 'companyCode='.self::company_code.'&uniqueAddressID='.$this->getAddressUniqueId().'&startDate='.$from_date->format('Y-m-d').'&endDate='.$to_date->format('Y-m-d'));
    $result = curl_exec($c);
    curl_close($c);

    $json = json_decode($result);
    $events = array();

    foreach( $json->dataList as $trash_type ) {
      foreach( $trash_type->pickupDates as $date ) {
        $events[] = array(
          'date'    => new DateTime($date),
          'summary' => $this->getTrashSummary($trash_type->_pickupTypeText)
        );
      }
    }

    return $events;
  }

  private function getTrashSummary($type) {
    switch($type) {
      case "GREY":
        return "Restafval wordt opgehaald";
      case "GREEN":
        return "GFT wordt opgehaald";
      case "PAPER":
        return "Papier wordt opgehaald";
      case "PACKAGES":
        return "Verpakkingen worden opgehaald";
      default:
        return "Onbekend afvaltype wordt opgehaald";
    }
  }

}

?>
