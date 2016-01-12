<!doctype html>
<html>

  <head>
    <title>Twente Milieu iCal</title>
    <style>
      form, div {
        margin: 20px auto;
        width: 250px;
        font: 14px Tahoma;
      }
      form input {
        float: right;
      }
    </style>
  </head>

  <body>

    <?php
      if ( $_GET['postcode'] && $_GET['huisnummer'] ) {
      require 'classes/twente_milieu.php';
      try {
        $afvalkalender = new TwenteMilieu($_GET['postcode'], $_GET['huisnummer']);
        $url = 'http://'.$_SERVER["SERVER_NAME"].dirname($_SERVER["REQUEST_URI"]);
        $url .= '/ical.php';
        $url .='?postcode='.$afvalkalender->safePostcode().'&huisnummer='.$afvalkalender->safeHuisnummer();
    ?>

        <div>
          <p>Voeg deze url toe aan je agenda:</p>
          <a href="<?php echo $url; ?>"><?php echo $url; ?></a>
        </div>

      <?php } catch ( Exception $e ) { ?>

        <div>
          <p>Sorry, je input is niet geldig :/</p>
          <p><?php echo $e->getMessage(); ?></p>
          <a href="index.php">Terug</a>
        </div>

      <?php } ?>

    <?php } else { ?>

      <form method="get" action="">
        <p>Postcode: <input type="text" name="postcode" value="" placeholder="1234AB"/></p>
        <p>Huisnummer: <input type="text" name="huisnummer" value="" placeholder="123"/></p>
        <input type="submit" value="Maak iCal link!"/>
      </form>

    <?php } ?>

  </body>

</html>
