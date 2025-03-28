#!/bin/bash

function delete_dist_bestanden() {
    find public/* -not -iname '*.php' -delete
}

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$projectdir" || exit 1

# Composer packages en PHP static analysis
export COMPOSER_NO_DEV=0
composer8.1 install || exit 1
composer8.1 check-platform-reqs || exit 1
php8.1 vendor/bin/parallel-lint \
    bin/ \
    install/ \
    public/ \
    src/php/ || exit 1
php8.1 vendor/bin/phpstan analyse || exit 1
php8.1 vendor/bin/phpcs --standard=ruleset.xml -n || exit 1

# Node environment
if [ ! -f ~/.nvm/nvm.sh ]; then
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash
fi
export NODE_ENV=development
. ~/.nvm/nvm.sh
nvm install node || exit 1
npm install npm@latest -g || exit 1

# npm packages
npm install || exit 1
npx update-browserslist-db@latest || exit 1
npm update update-browserslist-db || exit 1
npm audit fix

# webpack compilen
delete_dist_bestanden
npx tsc --noEmit || exit 1
npx webpack --config "webpack.dev.js" || exit 1
