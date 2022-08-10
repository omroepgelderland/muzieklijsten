# Muzieklijsten module
Webapp waarmee bezoekers nummers kunnen aanvragen en kunnen stemmen op toplijsten.

De productieomgeving is bitserver:/home/web/www/muzieklijsten/

## Interfaces
Stemlijst
https://web.gld.nl/muzieklijsten/muzieklijst.php?lijst=[lijst id]

Beheer van lijsten, plaatsen van nummers op een lijst, bekijken en beheren van resultaten.
https://web.gld.nl/muzieklijsten/admin.php

Losse nummer toevoegen aan de database, buiten Powergold om
https://web.gld.nl/muzieklijsten/los_toevoegen.html

## Deployment
Installeren met git
```sh
./deploy.sh
```

## Scheduled commands
Lijst activeren:
```mysql
CREATE EVENT muzieklijsten_stemmen_aan_[lijst id] ON SCHEDULE AT '1999-12-31 23:59:59' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE lijsten SET actief=1 WHERE id=[lijst id];
```

Lijst deactiveren:
```mysql
CREATE EVENT muzieklijsten_stemmen_uit_[lijst id] ON SCHEDULE AT '1999-12-31 23:59:59' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE lijsten SET actief=0 WHERE id=[lijst id];
```
