#!/bin/bash

# Updatescript voor de ontwikkelingsomgeving.
# Pullt alle tracked branches en update de database.

set -euo pipefail

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

php8.1 bin/update.php || rollback
