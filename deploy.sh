#!/bin/bash

function delete_dist_bestanden() {
    find public/* -not -iname '*.php' -delete
}

if [[ $1 == "" ]]; then
    mode="dev"
else
    mode="$1"
fi
projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
projectnaam="$(basename "$projectdir")"
tempdir="/tmp/dist_$projectnaam/"
cd "$projectdir" || exit 1

if [[ $2 != "kort" ]]; then
    if [ ! -f ~/.nvm/nvm.sh ]; then
        curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash
    fi
fi
export NODE_ENV=development
. ~/.nvm/nvm.sh
nvm install node || exit 1

if [[ $mode == "production" ]]; then
    oude_versie="$(git tag --list 'v*' --sort=v:refname | tail -n1)"
    echo "Versieverhoging? (major|minor|patch|premajor|preminor|prepatch|prerelease) "
    read -r versie_type
    nieuwe_versie="$(npx semver -i "$versie_type" "$oude_versie")"
    git_versie="v$nieuwe_versie"
fi

if [[ $mode == "dev" ]]; then
    if [[ $2 != "kort" ]]; then
        npm install npm@latest -g || exit 1
        npm install || exit 1
        npx update-browserslist-db@latest || exit 1
        npm audit fix
        export COMPOSER_NO_DEV=0
        /usr/local/bin/composer8.1 install || exit 1
        /usr/local/bin/composer8.1 check-platform-reqs || exit 1
        /usr/local/bin/composer8.1 dump-autoload || exit 1
        php8.1 vendor/bin/parallel-lint bin/ install/ public/ src/php/ || exit 1
        vendor/bin/phpstan analyse || exit 1
        php8.1 vendor/bin/phpcs --standard=ruleset.xml -n || exit 1
    fi
    delete_dist_bestanden
    npx tsc --noEmit || exit 1
    npx webpack --watch --config "webpack.$mode.js" || exit 1
fi
if [[ $mode == "production" || $mode == "staging" ]]; then
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
    /usr/local/bin/composer8.1 install || exit 1
    /usr/local/bin/composer8.1 dump-autoload || exit 1
    git add -f vendor/ || exit 1
    
    # Webpack output
    export NODE_ENV=development
    npm ci
    npx tsc --noEmit || exit 1
    npx webpack --config "webpack.$mode.js" || exit 1
    git add -f public/ || exit 1
    # export NODE_ENV=production
    # npm ci || exit 1
    # git add -f node_modules/  || exit 1

    # Dev bestanden eruit
    git rm -r \
        assets/ \
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
fi
