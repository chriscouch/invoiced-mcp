/* global moment */
(function () {
    'use strict';

    angular.module('app.auth').controller('LoginController', LoginController);

    LoginController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$window',
        'CurrentUser',
        'CSRF',
        'Core',
        'InvoicedConfig',
    ];

    function LoginController($scope, $state, $stateParams, $window, CurrentUser, CSRF, Core, InvoicedConfig) {
        Core.setTitle('Sign In');

        $('html').addClass('gray-bg');

        $scope.$on('userSignedOut', function () {
            // reload the page
            $window.location.reload();
        });

        $scope.email = $stateParams.email;
        $scope.baseUrl = InvoicedConfig.baseUrl;
        $scope.googleSignInUrl = InvoicedConfig.baseUrl + '/auth/google';
        $scope.intuitSignInUrl = InvoicedConfig.baseUrl + '/auth/intuit';
        $scope.microsoftSignInUrl = InvoicedConfig.baseUrl + '/auth/microsoft';
        $scope.xeroSignInUrl = InvoicedConfig.baseUrl + '/auth/xero';
        $scope.signUpUrl = InvoicedConfig.baseUrl + '/signup';
        $scope.login = login;
        $scope.remember = true;

        $scope.timeOfDay = 'other';
        let hour = parseInt(moment().format('H'));
        if (hour >= 4 && hour < 12) {
            // Morning = 4am - 12am
            $scope.timeOfDay = 'morning';
        } else if (hour >= 12 && hour < 18) {
            // Afternoon = 12am - 5pm
            $scope.timeOfDay = 'afternoon';
        } else if (hour >= 18 && hour < 21) {
            // Evening = 5pm - 9pm
            $scope.timeOfDay = 'evening';
        }

        function login(email, password, remember) {
            $scope.signingIn = true;
            $scope.error = false;
            $scope.attemptsRemaining = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.login(
                    {
                        username: email,
                        password: password,
                        remember: !!remember,
                    },
                    function (user) {
                        $scope.signingIn = false;

                        if (user.two_factor_required) {
                            $state.go('auth.verify_2fa');
                        } else if (user.redirect_to) {
                            $window.location = user.redirect_to;
                        } else {
                            $state.go('index');
                        }
                    },
                    function (result) {
                        $scope.signingIn = false;
                        $scope.error = result.data;

                        if ($scope.error.attempts_remaining) {
                            $scope.attemptsRemaining = $scope.error.attempts_remaining;
                        }

                        if ($scope.error.message.indexOf('could not find a match for that email address') !== -1) {
                            $scope.error.param = 'user_login_no_match';
                        }
                    },
                );
            });
        }
    }
})();
