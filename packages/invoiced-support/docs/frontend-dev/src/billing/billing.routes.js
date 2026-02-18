(function () {
    'use strict';

    angular.module('app.billing').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('billing', {
                abstract: true,
                url: '',
                template: '<ui-view/>',
                resolve: {
                    userBootstrap: userBootstrapPromise,
                },
                controller: [
                    '$scope',
                    '$state',
                    'CurrentUser',
                    function ($scope, $state, CurrentUser) {
                        $('html').addClass('gray-bg');

                        $scope.switchCompany = function (company) {
                            CurrentUser.useCompany(company);
                            $state.go('index');
                        };
                    },
                ],
            })
            .state('billing.canceled', {
                url: '/canceled',
                controller: 'CanceledAccountController',
                templateUrl: 'billing/views/canceled.html',
            })
            .state('billing.trial_ended', {
                url: '/trial_ended',
                templateUrl: 'billing/views/trial-ended.html',
                controller: 'TrialEndedController',
            });

        userBootstrapPromise.$inject = ['CurrentUser', 'Core', '$state'];

        function userBootstrapPromise(CurrentUser, Core, $state) {
            let promise = CurrentUser.get().$promise;

            promise.then(
                function (result) {
                    // check if 2FA verification is needed
                    if (result.two_factor_required) {
                        $state.go('auth.verify_2fa');
                        return;
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
