#!/bin/bash
# Updatescript voor de productieomgeving.
# Haalt een nieuwe versie van git, herstart services en werkt de database bij.

function rollback() {
    echo "update mislukt. Terugdraaien naar vorige versie."
    git reset --hard $vorige_git_hash
    exit 1
}

vorige_git_hash=$(git rev-parse HEAD)
git pull --rebase || exit 1
/usr/bin/php bin/update.php || rollback
