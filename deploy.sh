#!/bin/bash

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
nvm install --lts || exit 1
if [[ $npm_onveranderd != 0 ]]; then
	npm install npm@latest -g || exit 1
	npx browserslist@latest --update-db || exit 1
	npm install || exit 1
	md5sum package.json package-lock.json >package.md5
fi
rm -rf \
  public/afbeeldingen/ \
  public/css/ \
  public/fonts/ \
  public/js/ \
  public/*.html
npx webpack --config webpack.$mode.js || exit 1

if [[ $mode == "production" || $mode == "staging" ]]; then
	# echo "Versieverhoging? (major|minor|patch|premajor|preminor|prepatch|prerelease) "
	# read versie_type
	# npm --no-git-tag-version version "$versie_type" || exit 1
	versie="$(node -e "const fs = require('fs'); console.log(JSON.parse(fs.readFileSync('package.json', 'utf8')).version);")" || exit 1
	git_versie="v$versie"
	deze_branch="$(git rev-parse --abbrev-ref HEAD)"
	git branch -D $mode 2>/dev/null
	git push origin --delete $mode 2>/dev/null
	git push github --delete $mode 2>/dev/null
	git checkout -b $mode || exit 1
	git add -f public/ || exit 1
	git add -f vendor/ || exit 1
	git commit -m "[build] $git_versie" || exit 1
	git checkout "$deze_branch" || exit 1
	git tag "$git_versie" $mode || exit 1
	git push origin "$git_versie" || exit 1
	git push origin $mode || exit 1
	git push github "$git_versie" || exit 1
	git push github $mode || exit 1
	rm -rf \
		public/afbeeldingen/ \
		public/css/ \
		public/fonts/ \
		public/js/ \
		public/*.html \
		vendor/
fi
