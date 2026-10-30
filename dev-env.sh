#!/usr/bin/env bash
# Usage: source scripts/dev-env.sh

function path_unshift() {
    local dir="$1"
    if [[ ":${PATH:-}:" != *":$dir:"* ]]; then
        export PATH="$dir${PATH:+:$PATH}"
    fi
}

# Projectvariabelen
projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
projectnaam="$(basename "$projectdir")"
export projectdir projectnaam

# Initialize NVM
export NVM_DIR="$HOME/.nvm"
. "$NVM_DIR/nvm.sh"

# Prefer project-local Node.js executables
path_unshift "$projectdir/node_modules/.bin"

# Make PHP 8.1 the default PHP executable
DEV_BIN="$projectdir/.dev/bin"

mkdir -p "$DEV_BIN"
ln -sfn /usr/bin/php8.1 "$DEV_BIN/php"
ln -sfn /usr/local/bin/composer8.1 "$DEV_BIN/composer"

path_unshift "$DEV_BIN"
unset DEV_BIN

# Prefer project-local PHP and Composer executables
path_unshift "$projectdir/vendor/bin"

# Github
GH_TOKEN="$(cat "$HOME/.config/github-token")"
export GH_TOKEN
