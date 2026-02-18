```
$$$$$$\                               $$\                           $$\ 
\_$$  _|                              \__|                          $$ |
  $$ |  $$$$$$$\ $$\    $$\  $$$$$$\  $$\  $$$$$$$\  $$$$$$\   $$$$$$$ |
  $$ |  $$  __$$\\$$\  $$  |$$  __$$\ $$ |$$  _____|$$  __$$\ $$  __$$ |
  $$ |  $$ |  $$ |\$$\$$  / $$ /  $$ |$$ |$$ /      $$$$$$$$ |$$ /  $$ |
  $$ |  $$ |  $$ | \$$$  /  $$ |  $$ |$$ |$$ |      $$   ____|$$ |  $$ |
$$$$$$\ $$ |  $$ |  \$  /   \$$$$$$  |$$ |\$$$$$$$\ \$$$$$$$\ \$$$$$$$ |
\______|\__|  \__|   \_/     \______/ \__| \_______| \_______| \_______|
```

![CI](https://github.com/Invoiced/invoiced/workflows/CI/badge.svg)

This is the main repository for [Invoiced](https://invoiced.com).

## Developing

### Local development server

All development should be done in feature-specific branches (large changes or projects > 1 commit) or in the `dev` branch (small changes).

#### Requirements

- [PHP 8.3](http://php.net)
- [Composer](https://getcomposer.org/)
- [Docker](https://docker.com)
- redis PHP extension
- mailparse PHP extension

A `Dockerfile` has been included in the repo to run the application in Docker with a properly configured environment.

#### Setup Instructions

Follow these steps to set up a development server locally:

1. **Clone the project**

       git clone git@github.com:Invoiced/invoiced.git
       cd invoiced
       git checkout dev

2. **Install Composer Dependencies**

   PHP package management is handled by composer. In order to install all third-party PHP dependencies run:

       composer install

3. **Configure the environment**

   Run these commands:

       cp .env .env.local
       bin/console config:build

   The defaults should produce a working build but eventually you will need to populate `.env.local` with any keys and secrets for services you plan to use.

4. **Static Assets**

   Just once make sure the node dependencies are installed:

       npm install

   The static assets can be compiled with:

       grunt

5. **Start Docker**

   Execute this in the command line to spin up the development server:

       docker-compose up -d

6. **Database Migrations**

   Run the following command in the Docker container to setup the database schema:

       docker-compose exec php-fpm bin/console db:migrate

6. **Setup /etc/hosts**

   Add these lines to your `/etc/hosts` file on your host machine:

       127.0.0.1 invoiced.localhost
       127.0.0.1 api.invoiced.localhost
       127.0.0.1 {company_username}.invoiced.localhost <- repeated for each company you create

And done! Now the site can be accessed at [http://invoiced.localhost:1234](http://invoiced.localhost:1234). The Invoiced application should now be running correctly on your machine.

In order to access the dashboard you will need to follow the instructions at [https://github.com/invoiced/dashboard](https://github.com/invoiced/dashboard).

### Running the test suite

Start a shell in the Docker container:

	docker-compose exec php-fpm bash

Provision the test database (if you have not already done this):

	APP_ENV=test bin/console db:migrate

Then run the test suite:

	bin/phpunit

## Deploying

### GitHub Actions

Deploys happen from GitHub Actions. In this repository there is a script for deploying to our various environments.

In order to deploy all that is needed is a `git push` to the right branch (`production`, `sandbox`, or `dev`).

### Sandbox Environment

The sandbox environment is a mirror of production with a few minor differences. The sandbox can be accessed at [sandbox.invoiced.com](https://sandbox.invoiced.com). The sandbox servers run code deployed from the `sandbox` branch.
