#!/bin/bash

set -euo pipefail

if [ ! -f "$HOME/.nvm/nvm.sh" ]; then
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.8/install.sh | bash
fi

projectdir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$projectdir"
. "dev-env.sh"

npm install
npx --yes update-browserslist-db@latest
npm audit fix || :
npm outdated
