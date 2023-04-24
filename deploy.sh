#!/bin/bash

node_versie="18.15.0"
npm_versie="9.6.4"
md5sum --status -c package.md5 2>/dev/null
npm_onveranderd=$?

if [[ $1 == "" ]]; then
	mode="dev"
else
	mode="$1"
fi

/usr/local/bin/composer8.1 check-platform-reqs || exit 1
/usr/local/bin/composer8.1 install || exit 1
/usr/local/bin/composer8.1 dump-autoload || exit 1
if [ ! -f ~/.nvm/nvm.sh ]; then
	curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash
fi
. ~/.nvm/nvm.sh
if [[ $npm_onveranderd != 0 ]]; then
	nvm install $node_versie || exit 1
	. ~/.nvm/nvm.sh
	nvm exec $node_versie npm install npm@$npm_versie -g || exit 1
	nvm exec $node_versie npx browserslist@latest --update-db || exit 1
	nvm exec $node_versie npm install || exit 1
	md5sum package.json package-lock.json >package.md5
fi
rm -rf \
  public/afbeeldingen/ \
  public/css/ \
  public/fonts/ \
  public/js/ \
  public/*.html
nvm exec $node_versie npx webpack --config webpack.$mode.js || exit 1

if [[ $2 == "release" ]]; then
	git branch -D release 2>/dev/null
	git push origin --delete release 2>/dev/null
	git push github --delete release 2>/dev/null
	git checkout -b release || exit 1
	echo "Versienummer? (vX.X.X) "
	read versie
	git add -f public/ || exit 1
	git add -f vendor/ || exit 1
	git commit -m "[build] $releaseVersion" || exit 1
	git checkout master
	git tag "$versie" release
	git push origin "$version"
	git push origin release
	git push github "$version"
	git push github release
fi
