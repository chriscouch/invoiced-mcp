(function () {
    'use strict';

    angular.module('app.core').config(CoreConfig);

    CoreConfig.$inject = [
        '$urlRouterProvider',
        '$locationProvider',
        '$httpProvider',
        '$provide',
        '$compileProvider',
        '$translateProvider',
        'InvoicedConfig',
    ];

    function CoreConfig(
        $urlRouterProvider,
        $locationProvider,
        $httpProvider,
        $provide,
        $compileProvider,
        $translateProvider,
        InvoicedConfig,
    ) {
        angular.module('LocalStorageModule').constant('prefix', 'invoiced');

        $urlRouterProvider.otherwise('/');
        $locationProvider.html5Mode(true).hashPrefix('!');

        // disable debugging in template compiler
        if (InvoicedConfig.environment !== 'dev') {
            $compileProvider.debugInfoEnabled(false);
        }

        /* HTTP Setup */

        $httpProvider.defaults.withCredentials = true;

        // intercept http calls for custom handling
        $provide.factory('AppHttpInterceptor', httpInterceptor);
        $httpProvider.interceptors.push('AppHttpInterceptor');

        /* Translations */

        $translateProvider.useSanitizeValueStrategy('sanitize');
        angular.forEach(InvoicedConfig.translations, function (translations, key) {
            $translateProvider.translations(key, translations);
        });
        $translateProvider.determinePreferredLanguage();
        $translateProvider.fallbackLanguage('en');
    }

    httpInterceptor.$inject = [
        '$q',
        '$cookies',
        '$location',
        '$window',
        '$injector',
        'InvoicedConfig',
        'selectedCompany',
    ];

    function httpInterceptor($q, $cookies, $location, $window, $injector, InvoicedConfig, selectedCompany) {
        let showingLoginModal = false;
        let loginPromiseQueue = [];

        return {
            // On request success
            request: function (config) {
                // use JSON by default
                if (typeof config.headers.Accept === 'undefined') {
                    config.headers.Accept = 'application/json';
                }

                if (typeof config.noAuth === 'undefined' || !config.noAuth) {
                    // Use the company API key
                    if (
                        typeof selectedCompany.dashboard_api_key === 'string' &&
                        selectedCompany.dashboard_api_key.length > 0
                    ) {
                        let encoded = $window.btoa(selectedCompany.dashboard_api_key + ':');
                        config.headers.Authorization = 'Basic ' + encoded;
                        config.headers['X-App-Version'] = InvoicedConfig.version;
                        config.withCredentials = false;
                    }
                } else {
                    config.withCredentials = true;

                    // CSRF protection
                    let xsrfValue = $cookies[InvoicedConfig.csrfCookieName]; // NOTE this will deserialize the token into an object
                    xsrfValue = typeof xsrfValue === 'object' ? angular.toJson(xsrfValue) : xsrfValue; // so we might need to re-serialize it
                    if (xsrfValue && needsCsrfToken(config)) {
                        config.headers['X-CSRF-Token'] = xsrfValue;
                    }
                }

                // Return the config or wrap it in a promise if blank.
                return config || $q.when(config);
            },

            // On request failure
            requestError: function (rejection) {
                // Return the promise rejection.
                return $q.reject(rejection);
            },

            // On response success
            response: function (response) {
                // Return the response or promise.
                return response || $q.when(response);
            },

            // On response failure
            responseError: function (rejection) {
                let $state = $injector.get('$state');

                // if the status code is below 100 that indicates the request failed for
                // some reason, maybe a timeout or dns failure
                if (rejection.status < 100) {
                    handleConnectionError(rejection);
                } else if (rejection.status == 401) {
                    let promise = handle401(rejection, $state);
                    if (promise) {
                        return promise;
                    }
                }

                // ensure an error message envelope is present
                if (typeof rejection.data !== 'object' || !rejection.data) {
                    rejection.data = {
                        type: rejection.status >= 500 ? 'api_error' : 'invalid_request',
                        message: 'There was an error processing your request.',
                    };
                }

                // Return the promise rejection.
                return $q.reject(rejection);
            },
        };

        function needsCsrfToken(config) {
            // We are performing our own check to see if a CSRF
            // token should be injected into the request since
            // angular.js has hardcoded its own same origin checking
            // that does not follow CORS.
            return (
                config.url.indexOf(InvoicedConfig.baseUrl) === 0 &&
                (config.method === 'POST' ||
                    config.method === 'PUT' ||
                    config.method === 'PATCH' ||
                    config.method === 'DELETE')
            );
        }

        function handleConnectionError(rejection) {
            rejection.data = {
                type: 'api_error',
                message:
                    'We had trouble communicating with Invoiced. Sorry :( Please try again later or contact support@invoiced.com.',
            };
        }

        function handle401(rejection, $state) {
            if (typeof rejection.data !== 'object' || !rejection.data) {
                rejection.data = {
                    type: 'authorization_error',
                };
            }

            // If the user is just jumping into the application
            // and is not signed in then redirect to the login page.
            let CurrentUser = $injector.get('CurrentUser');
            if (!CurrentUser.hasSignedIn()) {
                return sendToLoginPage($state);
            }

            // If the user was previously signed in then
            // we want to show them a login modal without
            // leaving the page and retry the HTTP request.
            return showLoginModal(rejection, CurrentUser);
        }

        function sendToLoginPage($state) {
            // save redirect URL for after the user signs in
            let redirect = $cookies.redirect;
            if (
                !redirect ||
                redirect === '/' ||
                redirect === '/login' ||
                redirect === '/forgot' ||
                redirect === '/register'
            ) {
                redirect = $cookies.redirect = $location.url();
            }

            $state.go('auth.login');
        }

        function showLoginModal(rejection, CurrentUser) {
            let deferred = $q.defer();

            // When there are multiple HTTP requests
            // that failed with 401, add them to a queue
            // to prevent the login modal from popping up
            // multiple times.
            loginPromiseQueue.push({ deferred: deferred, rejection: rejection });
            if (showingLoginModal) {
                return deferred.promise;
            }

            showingLoginModal = true;

            let $modal = $injector.get('$modal');
            const modalInstance = $modal.open({
                templateUrl: 'auth/views/login-modal.html',
                controller: 'LoginModalController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
                windowClass: 'login-modal',
            });

            // After a successful login, we then need
            // to reload the current user. Once that's
            // complete the original request can be replayed.
            modalInstance.result.then(function () {
                showingLoginModal = false;

                CurrentUser.clear();
                let userPromise = CurrentUser.get().$promise;
                userPromise.then(
                    function () {
                        // now we can finally replay the original requests
                        // and resolve all waiting promises
                        replayAllLoginRequests();
                    },
                    function (result) {
                        if (result.data && result.data.message) {
                            let Core = $injector.get('Core');
                            Core.showFailedMessage(result.data.message);
                        }

                        // resolve all waiting promises
                        angular.forEach(loginPromiseQueue, function (item) {
                            item.deferred.reject(item.rejection);
                        });

                        loginPromiseQueue = [];
                    },
                );
            });

            return deferred.promise;
        }

        function replayAllLoginRequests() {
            let $http = $injector.get('$http');
            let selectedCompany = $injector.get('selectedCompany');
            angular.forEach(loginPromiseQueue, function (item) {
                // update the request with the new API key
                // before replaying
                item.rejection.config.headers.Authorization = selectedCompany.auth_header;

                $http(item.rejection.config).then(
                    function success(response) {
                        item.deferred.resolve(response);
                    },
                    function error(response) {
                        item.deferred.reject(response);
                    },
                );
            });

            loginPromiseQueue = [];
        }
    }
})();
