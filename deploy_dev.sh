#!/bin/bash

function exit_handler() {
    trap - INT TERM EXIT
    if declare -p pids &>/dev/null && ((${#pids[@]} > 0)); then
        kill "${pids[@]}" || :
        wait "${pids[@]}" || :
    fi
}

function delete_dist_bestanden() {
    find public/* -not -iname '*.php' -delete
}

function php_codesniffer() {
    if ! php8.1 vendor/bin/phpcs --standard=ruleset.xml -n; then
        echo "Fixen met phpcbf? (j/n)"
        read -r ans
        if [[ $ans != "j" ]]; then
            exit 1
        else
            php8.1 vendor/bin/phpcbf --standard=ruleset.xml -n || :
            php8.1 vendor/bin/phpcs --standard=ruleset.xml -n
        fi
    fi
}

set -euo pipefail

watch_mode=false
for arg in "$@"; do
    case "$arg" in
        --watch)
            watch_mode=true
            ;;
        *)
            echo "Onbekend argument: $arg"
            echo "Gebruik: $0 [--watch]"
            exit 1
            ;;
    esac
done

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$projectdir"

# Composer packages en PHP static analysis
export COMPOSER_NO_DEV=0
composer8.1 install
composer8.1 check-platform-reqs
php8.1 vendor/bin/parallel-lint \
    --exclude node_modules/ \
    --exclude vendor/ \
    ./
php8.1 vendor/bin/phpstan analyse
php_codesniffer

# Node environment
if [ ! -f "$HOME/.nvm/nvm.sh" ]; then
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
fi
export NODE_ENV=development
. "$HOME/.nvm/nvm.sh"
nvm install node
npm install npm@latest -g

# npm packages
npm install
npm update update-browserslist-db
npx update-browserslist-db@latest
npm audit fix || :

# webpack compilen
delete_dist_bestanden
# npx eslint
npx prettier --write .
npx prettier --write --ignore-path .prettierignore config/config.json

if [[ "$watch_mode" == true ]]; then
    pids=()

    trap exit_handler INT TERM EXIT

    npx tsc-watch --noEmit --noClear &
    pids+=($!)

    npx webpack --watch --config "webpack.dev.js" &
    pids+=($!)

    wait -n "${pids[@]}"
else
    npx tsc --noEmit
    npx webpack --config "webpack.dev.js"
fi
