#!/bin/bash

set -euo pipefail

mode="$1"
if [[ $mode != "production" && $mode != "staging" ]]; then
    echo "geen mode gespecifeerd"
    exit 1
fi

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
projectnaam="$(basename "$projectdir")"
tempdir="/tmp/dist_$projectnaam/"
cd "$projectdir"

current_branch=$(git rev-parse --abbrev-ref HEAD)
if [[ $(git rev-parse --abbrev-ref HEAD) != "master" ]]; then
    echo "Op branch $current_branch ipv master. Toch doorgaan? (j/n)"
    read -r ans
    if [[ $ans != "j" ]]; then
        exit 1
    fi
fi

./deploy_dev.sh

if [ -n "$(git status --untracked-files=no --porcelain)" ]; then
    git status
    echo "Er zijn uncommitted changes. Toch doorgaan? (j/n)"
    read -r ans
    if [[ $ans != "j" ]]; then
        exit 1
    fi
fi

# nvm environment
# shellcheck disable=SC1091
. "$HOME/.nvm/nvm.sh"
nvm install node

# versieverhoging
if [[ $mode == "production" ]]; then
    oude_versie="$(git tag --list 'v*' --sort=v:refname | tail -n1)"
    if [[ -z $oude_versie ]]; then
        nieuwe_versie="0.1.0"
    else
        echo "De huidige versie is $oude_versie. Versieverhoging? (major|minor|patch|premajor|preminor|prepatch|prerelease) "
        read -r versie_type
        nieuwe_versie="$(npx semver -i "$versie_type" "$oude_versie")"
    fi
    git_versie="v$nieuwe_versie"
fi

git branch -D "$mode" 2>/dev/null || :
git push origin --delete "$mode" 2>/dev/null || :
git push github --delete "$mode" 2>/dev/null || :
git gc
rm -rf "$tempdir"
git clone . "$tempdir"
cd "$tempdir"
git checkout -b "$mode"

# Composer packages
export COMPOSER_NO_DEV=1
composer8.1 install --optimize-autoloader
git add -f vendor/

# Webpack output
export NODE_ENV=development
npm ci
npx webpack --config "webpack.$mode.js"
git add -f public/

# Dev bestanden eruit
git rm -r \
    assets/ \
    deploy_dev.sh \
    deploy.sh \
    package-lock.json \
    package.json \
    phpstan.dist.neon \
    ruleset.xml \
    src/html/ \
    src/js/ \
    src/scss/ \
    test/ \
    tsconfig.json \
    webpack.*
if [[ $mode == "production" ]]; then
    git commit -m "[build] $git_versie"
    git tag "$git_versie"
    git push origin "$git_versie"
fi
if [[ $mode == "staging" ]]; then
    git commit -m "[staging build]"
fi
git push origin "$mode"
cd "$projectdir"
rm -rf "$tempdir"
if [[ $mode == "production" ]]; then
    git push origin "$git_versie"
    git push github "$git_versie"
fi
git push --force origin "$mode"
git push --force github "$mode"
if [[ $current_branch == "master" ]]; then
    git push origin
    git push github
fi
