#!/bin/bash

set -euo pipefail

# Node environment
if [ ! -f "$HOME/.nvm/nvm.sh" ]; then
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
fi
export NODE_ENV=development
. "$HOME/.nvm/nvm.sh"
nvm install node
npm install npm@latest -g

npm install
npm update update-browserslist-db
npx update-browserslist-db@latest
npm audit fix || :
npm outdated
