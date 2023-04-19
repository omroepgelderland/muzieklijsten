#!/bin/bash

scriptdir=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
projectdir="$(dirname "$scriptdir")"
mkdir -p "$projectdir/data/log"
chmod -R o+r "$projectdir/public/" "$projectdir/src/"
setfacl -R \
    -m u:$USER:rwx,u:www-data:rwx \
    -dm u:$USER:rwX,u:www-data:rwX \
    data
setfacl -R \
    -m u:$USER:rwX,u:www-data:rX \
    config
find "$projectdir/public/" "$projectdir/src/" -type d -exec chmod +x {} \;
(cd "$projectdir" && composer check-platform-reqs) || exit 1
(cd "$projectdir" && composer install) || exit 1
(cd "$projectdir" && composer dump-autoload) || exit 1
(cd "$projectdir" && php install/install.php) || exit 1
if ! crontab -l 2>/dev/null | grep -q "anonimiseer.php"; then
    (crontab -l 2>/dev/null ; echo "@weekly /usr/bin/php \"$projectdir/bin/anonimiseer.php\" #(muzieklijsten) Anonimiseert stemmers.") | crontab -
fi
