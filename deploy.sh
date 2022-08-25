#!/bin/bash

md5sum --status -c package-lock.json.md5 2>/dev/null
npm_onveranderd=$?
vorige_git_hash=$(git rev-parse HEAD)

if [[ "$(hostname)" == "prod" ]]; then
	# Productie
	composercmd="composer"
	git pull || exit 1
	$composercmd check-platform-reqs || exit 1
	$composercmd install || exit 1
	if [[ $npm_onveranderd == 1 ]]; then
		npm ci || exit 1
		md5sum package-lock.json>package-lock.json.md5
	fi
	npx webpack --config webpack.prod.js || exit 1
elif [[ "$(hostname)" == "og-webdev1" ]]; then
	# dev op devserver
	node_versie="12.22.9"
	composercmd="/usr/local/bin/composer8.1"
	$composercmd check-platform-reqs || exit 1
	$composercmd install || exit 1
	. ~/.nvm/nvm.sh
	if [[ $npm_onveranderd == 1 ]]; then
		nvm install $node_versie || exit 1
		. ~/.nvm/nvm.sh
		nvm exec $node_versie npm ci || exit 1
		md5sum package-lock.json>package-lock.json.md5
	fi
	rm -rf public/afbeeldingen/ public/css/ public/fonts/ public/js/
	nvm exec $node_versie npx webpack --config webpack.dev.js || exit 1
fi
