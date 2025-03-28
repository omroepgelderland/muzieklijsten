#!/bin/bash

mode="$1"
if [[ $mode != "production" && $mode != "staging" ]]; then
    echo "geen mode gespecifeerd"
    exit 1
fi

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
projectnaam="$(basename "$projectdir")"
tempdir="/tmp/dist_$projectnaam/"
cd "$projectdir" || exit 1

if [[ $(git rev-parse --abbrev-ref HEAD) != "master" ]]; then
    echo "Not on branch master"
    exit 1
fi

./deploy_dev.sh || exit 1

if [ -n "$(git status --untracked-files=no --porcelain)" ]; then
    git status
    echo "Er zijn uncommitted changes. Toch doorgaan? (j/n)"
    read -r ans
    if [[ $ans != "j" ]]; then
        exit 1
    fi
fi

# nvm environment
. ~/.nvm/nvm.sh
nvm install node || exit 1

# versieverhoging
if [[ $mode == "production" ]]; then
    oude_versie="$(git tag --list 'v*' --sort=v:refname | tail -n1)"
    echo "De huidige versie is $oude_versie. Versieverhoging? (major|minor|patch|premajor|preminor|prepatch|prerelease) "
    read -r versie_type
    nieuwe_versie="$(semver -i "$versie_type" "$oude_versie")"
    git_versie="v$nieuwe_versie"
fi

git branch -D "$mode" 2>/dev/null
git push origin --delete "$mode" 2>/dev/null
git push github --delete "$mode" 2>/dev/null
git gc
rm -rf "$tempdir"
git clone . "$tempdir" || exit 1
cd "$tempdir" || exit 1
git checkout -b "$mode"

# Composer packages
export COMPOSER_NO_DEV=1
composer8.1 install --optimize-autoloader || exit 1
git add -f vendor/ || exit 1

# Webpack output
export NODE_ENV=development
npm ci || exit 1
npx webpack --config "webpack.$mode.js" || exit 1
git add -f public/ || exit 1

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
    webpack.* || exit 1
if [[ $mode == "production" ]]; then
    git commit -m "[build] $git_versie" || exit 1
    git tag "$git_versie" || exit 1
    git push origin "$git_versie" || exit 1
fi
if [[ $mode == "staging" ]]; then
    git commit -m "[staging build]" || exit 1
fi
git push origin "$mode" || exit 1
cd "$projectdir" || exit 1
rm -rf "$tempdir"
if [[ $mode == "production" ]]; then
    git push origin "$git_versie" || exit 1
    git push github "$git_versie" || exit 1
fi
git push --force origin "$mode" || exit 1
git push --force github "$mode" || exit 1
