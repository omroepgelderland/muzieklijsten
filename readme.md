# Muzieklijsten module
Webapp waarmee bezoekers nummers kunnen aanvragen en kunnen stemmen op toplijsten.

De productieomgeving is bitserver:/home/web/www/muzieklijsten/

## Interfaces
Stemlijst
https://web.omroepgelderland.nl/muzieklijsten/muzieklijst.php?lijst=[lijst id]

Beheer van lijsten en het plaatsen van nummers op een lijst
https://web.omroepgelderland.nl/muzieklijsten/admin.php

Andere beheerinterface?
https://web.omroepgelderland.nl/muzieklijsten/beheer.php

Import uit Powergold?
https://web.omroepgelderland.nl/muzieklijsten/import.php

Losse nummer toevoegen aan de database, buiten Powergold om
https://web.omroepgelderland.nl/muzieklijsten/los_toevoegen.html

Stemresultaten van een lijst
https://web.omroepgelderland.nl/muzieklijsten/resultaten.php?lijst=[lijst id]

Nummer die in een lijst staan?
https://web.omroepgelderland.nl/muzieklijsten/selected.php?lijst=[lijst id]

## Deployment
Installeren met git
`composer install`
