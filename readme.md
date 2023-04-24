# Muzieklijsten module
Webapp waarmee bezoekers nummers kunnen aanvragen en kunnen stemmen op toplijsten.

## Interfaces
Stemlijst
[url root]/index.php?lijst=[lijst id]
[url root]?lijst=[lijst id]

Beheer van lijsten, plaatsen van nummers op een lijst, bekijken en beheren van resultaten.
[url root]/admin.html

Losse nummers toevoegen aan de database.
[url root]/los_toevoegen.html

## Installatie
Alleen voor Linux.
Benodigde paketten:
    - PHP 8.1 of hoger
    - PHP mysqli
    - Composer
    - MySQL- of MariaDB-server
Installatie van dependencies op Ubuntu 22.04:
```sh
sudo apt install composer php mariadb-server php-mysql acl cron
```
Voer het installatiescript install/install.sh uit.
Configureer je webserver.
Importeer nummers.

## Nummers invoeren

### Uit powergold
Exporteer de database uit Powergold in Excel (xls) formaat.
Roep aan vanaf de commandline:
`php [projectdir]/import_powergold.php [pad naar excelsheet]`

### Handmatig
Beheerders kunnen losse nummers toevoegen op de pagina los_toevoegen.html

### Toevoegen in MySQL
Nieuwe nummers kunnen worden ingevoerd in de tabel nummers.

## Scheduled commands
Je kunt gebruik maken van MySQL scheduled events om een stemlijst op een specifieke tijd aan of uit te zetten.

### Lijst activeren:
```mysql
CREATE EVENT muzieklijsten_stemmen_aan_[lijst id] ON SCHEDULE AT '1999-12-31 23:59:59' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE lijsten SET actief=1 WHERE id=[lijst id];
```

### Lijst deactiveren:
```mysql
CREATE EVENT muzieklijsten_stemmen_uit_[lijst id] ON SCHEDULE AT '1999-12-31 23:59:59' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE lijsten SET actief=0 WHERE id=[lijst id];
```
