#!/bin/bash

set -e # exit with nonzero exit code if anything fails

if [[ "${GITHUB_REF_NAME}" == "dev" ]]
then
	SITE="app.staging.invoiced.com"
	CONFIG_FILE="config.staging.js"
	ENVIRONMENT="staging"
elif [[ "${GITHUB_REF_NAME}" == "sandbox" ]]
then
  SITE="app.sandbox.invoiced.com"
	CONFIG_FILE="config.sandbox.js"
	ENVIRONMENT="sandbox"
elif [[ "${GITHUB_REF_NAME}" == "production" ]]
then
	SITE="app.invoiced.com"
	CONFIG_FILE="config.production.js"
	ENVIRONMENT="production"
fi

BASTION="52.14.71.55"
APPSERVERS=( "66.228.52.252" "74.207.249.65" "50.116.54.141" )
BASTION_USER="deploy"
DEPLOY_USER="deploy" 
DEPLOY_DIR="/var/www/${SITE}"
VERSION=`git rev-parse --short HEAD`

# Copy config file1
echo "Using ${CONFIG_FILE} config file"
cp ${CONFIG_FILE} config.js

# Compile assets using the deployment config
echo "Compiling assets"
./version.sh
grunt release

# Deploy to app servers
cd build
for HOST in "${APPSERVERS[@]}"
do
	echo "Deploying ${VERSION} to ${HOST}:${DEPLOY_DIR}"
	rsync -avzOq -e "ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -q -A ${BASTION_USER}@${BASTION} ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -q" ./ ${DEPLOY_USER}@${HOST}:${DEPLOY_DIR} --delete

	ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -q -A ${BASTION_USER}@${BASTION} ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -q ${DEPLOY_USER}@${HOST} bash <<EOF
# Ensure correct permissions
sudo chown -R deploy:deploy /var/www
sudo chmod -R 770 /var/www
EOF
done
