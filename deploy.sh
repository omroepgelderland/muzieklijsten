#!/bin/bash

node_versie="18.15.0"
npm_versie="9.6.2"
md5sum --status -c package.md5 2>/dev/null
npm_onveranderd=$?
vorige_git_hash=$(git rev-parse HEAD)

if [[ "$(hostname)" == "app.gld.nl" && "$PWD" == "/home/muzieklijsten/prod" ]]; then
	env="prod"
	composercmd="composer"
elif [[ "$(hostname)" == "app.gld.nl" && "$PWD" == "/home/muzieklijsten/staging" ]]; then
	env="staging"
	composercmd="composer"
else
	env="dev"
	composercmd="/usr/local/bin/composer8.1"
fi

if [[ $env == "prod" || $env == "staging" ]]; then
	deploy_md5_voor=$(md5sum deploy.sh 2>/dev/null)
	git pull || exit 1
	deploy_md5_na=$(md5sum deploy.sh 2>/dev/null)
	if [[ $deploy_md5_voor != $deploy_md5_na ]]; then
		# Deploy script zelf is veranderd.
		./deploy.sh
		exit
	fi
fi
$composercmd check-platform-reqs || exit 1
$composercmd install || exit 1
$composercmd dump-autoload
if [ ! -f ~/.nvm/nvm.sh ]; then
	curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash
fi
. ~/.nvm/nvm.sh
if [[ $npm_onveranderd != 0 ]]; then
	nvm install $node_versie || exit 1
	. ~/.nvm/nvm.sh
	nvm exec $node_versie npm install npm@$npm_versie -g || exit 1
	if [[ $env == "dev" ]]; then
		nvm exec $node_versie npx browserslist@latest --update-db
		nvm exec $node_versie npm install || exit 1
	else
		nvm exec $node_versie npm ci || exit 1
	fi
	md5sum package.json package-lock.json >package.md5
fi
if [[ $env == "dev" ]]; then
	rm -rf \
		public/afbeeldingen/ \
		public/css/ \
		public/fonts/ \
		public/js/ \
		public/index.html \
		public/los_toevoegen.html
fi
nvm exec $node_versie npx webpack --config webpack.$env.js || exit 1
