(function () {
    'use strict';

    angular.module('app.core').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            //
            // Any state underneath `manage` will require the
            // user to be authenticated.
            //

            .state('manage', {
                abstract: true,
                url: '',
                templateUrl: 'core/views/manage.html',
                controller: 'ManageController',
                resolve: {
                    userBootstrap: userBootstrapPromise,
                },
            })

            //
            // Routing state - directs user towards correct main page.
            //
            .state('manage.index', {
                url: '/index',
                controller: 'ManageIndexController',
            })

            //
            // Default State - visible to everyone
            //

            .state('index', {
                url: '/',
                controller: 'IndexController',
            })

            //
            // No Companies - Signed in users that don't have any companies
            //

            .state('no_companies', {
                url: '/no-companies',
                templateUrl: 'core/views/no-companies.html',
                controller: 'NoCompaniesController',
            });

        userBootstrapPromise.$inject = [
            '$state',
            '$location',
            '$window',
            '$cookies',
            'CurrentUser',
            'Company',
            'Core',
            'selectedCompany',
        ];

        function userBootstrapPromise(
            $state,
            $location,
            $window,
            $cookies,
            CurrentUser,
            Company,
            Core,
            selectedCompany,
        ) {
            let queryParams = $location.search();
            if (typeof queryParams.account !== 'undefined') {
                $cookies.selectedCompany = queryParams.account;
            }

            let promise = CurrentUser.get().$promise;

            promise.then(
                function (result) {
                    // check if 2FA verification is needed
                    if (result.two_factor_required) {
                        $state.go('auth.verify_2fa');
                        return;
                    }

                    let companies = CurrentUser.companies;

                    // no companies on this user account, show a warning
                    if (companies.length === 0) {
                        $state.go('no_companies');

                        return;
                    }

                    // the company needs to reactivate a canceled account
                    if (selectedCompany.billing.status === 'canceled') {
                        $state.go('billing.canceled');
                        return;
                    }

                    // the company needs to choose a plan because their trial ended
                    if (selectedCompany.billing.status === 'unpaid') {
                        $state.go('billing.trial_ended');
                        return;
                    }

                    // the company needs to finish onboarding
                    if (typeof selectedCompany.onboarding_url !== 'undefined') {
                        $window.location = selectedCompany.onboarding_url;
                        return;
                    }

                    // set the time zone, if not already set
                    if (!selectedCompany.time_zone) {
                        Core.determineTimezone(function (tzName) {
                            Company.edit(
                                {
                                    id: selectedCompany.id,
                                },
                                {
                                    time_zone: tzName,
                                },
                            );

                            selectedCompany.time_zone = tzName;
                        });
                    }
                },
                function (result) {
                    if (result.data && result.data.message) {
                        Core.showFailedMessage(result.data.message);
                    }
                },
            );

            return promise;
        }
    }
})();
