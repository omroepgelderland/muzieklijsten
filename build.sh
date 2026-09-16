#!/bin/bash

set -euo pipefail

GH_TOKEN="$(cat "$HOME/.config/github-token")"
export GH_TOKEN

mode="$1"
if [[ $mode != "production" && $mode != "staging" ]]; then
    echo "geen mode gespecifeerd"
    exit 1
fi

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
projectnaam="$(basename "$projectdir")"
build_dir="/tmp/${USER}_build_$projectnaam/"
cd "$projectdir"

current_branch=$(git rev-parse --abbrev-ref HEAD)
if [[ $(git rev-parse --abbrev-ref HEAD) != "master" ]]; then
    if [[ $mode == "production" ]]; then
        echo "Op branch $current_branch ipv master."
        exit 1
    else
        echo "Op branch $current_branch ipv master. Toch doorgaan? (j/n)"
        read -r ans
        if [[ $ans != "j" ]]; then
            exit 1
        fi
    fi
fi

./build-dev.sh

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
    oude_versie="$(git tag --list 'v*' --sort=v:refname | tail -n1 || :)"
    if [[ -z $oude_versie ]]; then
        nieuwe_versie="0.1.0"
    else
        echo "De huidige versie is $oude_versie. Versieverhoging? (major|minor|patch|premajor|preminor|prepatch|prerelease) "
        read -r versie_type
        nieuwe_versie="$(npx semver -i "$versie_type" "$oude_versie")"
    fi
    git_versie="v$nieuwe_versie"
    archive="build/${projectnaam}-${git_versie}.tar.gz"
    releases_dir="/home/git/releases/${projectnaam}/production"
    releases_archive="${releases_dir}/${git_versie}"
    git tag "$git_versie"
    git push origin
    git push github
    git push origin "$git_versie"
    git push github "$git_versie"
else
    commit="$(git rev-parse --short=12 HEAD)"
    archive="build/${projectnaam}-staging-${commit}.tar.gz"
    releases_dir="/home/git/releases/${projectnaam}/staging"
    releases_archive="${releases_dir}/${commit}"
fi

# build

rm -rf "$build_dir"
git clone . "$build_dir"
cd "$build_dir"

if [[ $mode == "production" ]]; then
    cat > VERSION <<EOF
VERSION=${git_versie}
EOF
fi
cat >> VERSION <<EOF
COMMIT=$(git rev-parse HEAD)
BUILD_DATE=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF

# Composer packages
composer8.1 install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# Webpack output
export NODE_ENV=development
npm ci
npx webpack --config "webpack.$mode.js"

mkdir "build"
tar -czf "$archive" \
    --exclude-from=.release-exclude \
    --exclude="build" \
    .
sha256sum "$archive" > "${archive}.sha256"

ssh git@git.gld.nl "
    set -e

    if [ -e '${releases_archive}' ]; then
        echo 'Release ${releases_archive} already exists' >&2
        exit 1
    fi

    mkdir -p '${releases_archive}'
"
rsync -av \
    "${archive}" \
    "${archive}.sha256" \
    "git@git.gld.nl:${releases_archive}/"
if [[ $mode == "production" ]]; then
    gh release create "$git_versie" \
        "$archive" \
        --verify-tag \
        --generate-notes
fi
ssh git@git.gld.nl \
    "ln -sfn '${releases_archive}' '${releases_dir}/latest'"

cd "$projectdir"
rm -rf "$build_dir"
