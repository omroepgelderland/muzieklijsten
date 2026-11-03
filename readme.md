# Muzieklijstenmodule

Webapp waarmee bezoekers nummers kunnen aanvragen en kunnen stemmen op toplijsten.

## Interfaces

Stemlijst  
index.html?lijst=[lijst id]

Beheer van lijsten, plaatsen van nummers op een lijst, bekijken en beheren van resultaten.  
admin.html

Losse nummers toevoegen aan de database.  
los_toevoegen.html

## Installatie

Alleen voor Linux.  
Benodigde paketten:

- PHP 8.1 of hoger
- PHP mysqli
- php-intl
- MySQL- of MariaDB-server

Installatie van dependencies op Ubuntu 22.04:

```sh
sudo apt install php mariadb-server php-mysql php-intl acl cron
```

Voer het installatiescript install/install.sh uit.  
Configureer je webserver. Zorg dat apache leesrechten heeft op de projectmap.  
Importeer nummers.

## Updaten

<del>Haal de nieuwste versie van github binnen door `./update.sh` in de projectroot uit te voeren.</del>

## Nummers invoeren

### Uit powergold

Exporteer de database uit Powergold in Excel (xls) formaat.  
Roep aan vanaf de commandline:
`php [projectdir]/import_powergold.php [pad naar excelsheet]`

### Handmatig

Beheerders kunnen losse nummers toevoegen op de pagina los_toevoegen.html

### Toevoegen in MySQL

Nieuwe nummers kunnen worden ingevoerd in de tabel nummers.
