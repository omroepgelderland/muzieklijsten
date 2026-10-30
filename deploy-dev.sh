#!/bin/bash

# Updatescript voor de ontwikkelingsomgeving.
# Pullt alle tracked branches en update de database.

set -euo pipefail

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$projectdir"
. "dev-env.sh"

git fetch \
    --all \
    --tags \
    --prune \
    --prune-tags

current_branch=$(git branch --show-current)

git for-each-ref --format='%(refname:short) %(upstream:short)' refs/heads/ |
while read -r branch upstream; do
    [ -n "$upstream" ] || continue

    if [ "$branch" = "$current_branch" ]; then
        git merge --ff-only "$upstream"
    elif git merge-base --is-ancestor "$branch" "$upstream"; then
        git branch -f "$branch" "$upstream"
    else
        echo "Skipping $branch: cannot fast-forward to $upstream"
    fi
done

# Mappen aanmaken en juiste permissies instellen
if getent passwd www-data > /dev/null; then
    APACHE_USER="www-data"
elif getent passwd apache > /dev/null; then
    APACHE_USER="apache"
else
    echo "Geen geschikte Apache-gebruiker gevonden." >&2
    exit 1
fi
mkdir -p "data/log"
setfacl \
        -m "u:$USER:rwX,u:$APACHE_USER:rwX,d:u:$USER:rwX,d:u:$APACHE_USER:rwX" \
        "data/log"

# Deployment
php bin/deploy.php || rollback
