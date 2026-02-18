#/bin/sh

# set current directory to directory of script
cd ${0%/*}

VERSION=`git rev-parse --short HEAD`

echo -e "Setting version to: "$VERSION
echo  -e "(function() {
	'use strict';

	this.InvoicedConfig.version='"$VERSION"';
}.call(this));" > config/version.js