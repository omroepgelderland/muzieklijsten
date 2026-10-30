#!/bin/bash

function exit_handler() {
    trap - INT TERM EXIT
    if declare -p pids &>/dev/null && ((${#pids[@]} > 0)); then
        kill "${pids[@]}" || :
        wait "${pids[@]}" || :
    fi
}

function php_codesniffer() {
    local options="--standard=phpcs.xml.dist -n --colors --report-width=auto"
    if ! phpcs $options; then
        echo "Fixen met phpcbf? (j/n)"
        read -r ans
        if [[ $ans != "j" ]]; then
            exit 1
        else
            phpcbf $options
        fi
    fi
}

function _eslint() {
    if ! npx eslint; then
        echo "Fixen met eslint? (j/n)"
        read -r ans
        if [[ $ans != "j" ]]; then
            exit 1
        else
            npx eslint --fix || :
            npx eslint
        fi
    fi
}

set -euo pipefail

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$projectdir"
. "dev-env.sh"

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

# Composer packages en PHP static analysis
composer install
composer check-platform-reqs
parallel-lint \
    --exclude node_modules/ \
    --exclude vendor/ \
    ./
phpstan analyse
php_codesniffer

# npm packages
npm ci

# webpack compilen
# _eslint
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

php bin/deploy.php
