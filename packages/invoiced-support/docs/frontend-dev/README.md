Invoiced Dashboard
==================

![CI](https://github.com/Invoiced/dashboard/workflows/CI/badge.svg)

Web app for Invoiced available at [dashboard.invoiced.com](https://dashboard.invoiced.com).

## System Requirements

The dashboard works with the following web browsers:

- Recent Chrome or Firefox (not sure exact version)
- Safari 6+
- IE 10+

## Developing

### Requirements

- node.js

#### Setup Instructions

Follow these steps to run the dashboard locally:

1. **Clone the project**

       git clone git@github.com:Invoiced/dashboard.git
       cd dashboard
       git checkout dev

2. **Setup /etc/hosts**

	Add this line to your `/etc/hosts` file

       127.0.0.1 app.invoiced.localhost

3. **App configuration**

       cp config.dev.js config.js

4. **Start the development server**

	In order to install all dependencies with npm, compile assets with grunt, and start a dev server run this command:

       npm start

	You should now be able to access [http://app.invoiced.localhost:1236](http://app.invoiced.localhost:1236).

### Build Tools / Package Management

#### NPM

The dev dependencies and build tools are installed with NPM:

	npm install

#### Bower

External front-end dependencies are managed with [Bower](http://bower.io) when possible. The bower dependencies can be installed with:

	bower update

#### Grunt

Front-end assets are assembled with grunt:

	grunt

or for a production-ready compilation:

	grunt release

### Code Style

It is recommended to follow [angular-styleguide](https://github.com/johnpapa/angular-styleguide) created by @john_papa.

In order to maintain a high level of quality in the codebase we use [jshint](http://jshint.com/) and [js-beautify](https://github.com/beautify-web/js-beautify). They can be ran with the following commands:

	./beautify
	jshint src

## Deploying

The `sandbox` branch deploys to dashbaord.sandbox.invoiced.com. The `production` branch deploys to dashboard.invoiced.com. All that is required is a `git push` on the appropriate branch.

GitHub Actions automatically build and deploy the application. Or, the application can be deployed with this to production:

	grunt deploy:production

or to staging:

	grunt deploy:staging
