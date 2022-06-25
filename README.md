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
 * De mobiele app van Twente Milieu installeren
 * Een PDF downloaden en op een dode boom printen voor op de koelkast
 * Elke dag naar de website gaan

Ik heb gekozen voor optie nummer drie. Maar dan wel geautomatiseerd. En met een iCal feed ;)

## Features

 * Genereer een URL om aan je digitale agenda toe te voegen (dus geen download)
 * De kalender wordt dagelijks ververst, en daarna gecached zodat we Twente Milieu niet platspammen
 * Wijzigingen en verwijderingen in de agenda worden netjes overgenomen dmv `UIDs` zonder menselijke interventie
 * Ophaaldata worden ~drie maanden vooruit getoond
 * Stand-alone doodsimpele PHP code die nog draait op een schoenendoos

## Installatie

Nodig:
 * Git
 * Webserver die PHP draait
 * libcurl

Grofweg dit dus:
```bash
$ sudo apt-get install git apache2 libapache2-mod-php5 php5-curl
```

En dan:
```bash
tmpdir=$(mktemp -d)
cd $tmpdir
git clone https://github.com/Timendus/afvalkalender.git
sudo mv afvalkalender /var/www
cd /var/www/afvalkalender
sudo chown -R root:root .
sudo chown -R www-data:www-data cache
```

## Contact

### Feedback / lof / kritiek?
mail@timendus.com

### Verbeteringen?
Ik zie de pull request wel verschijnen ;)
