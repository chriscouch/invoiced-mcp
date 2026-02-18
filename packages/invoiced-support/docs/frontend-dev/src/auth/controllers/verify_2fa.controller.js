(function () {
    'use strict';

    angular.module('app.auth').controller('Verify2FAController', Verify2FAController);

    Verify2FAController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$window',
        'CurrentUser',
        'CSRF',
        'Core',
        'InvoicedConfig',
    ];

    function Verify2FAController($scope, $state, $stateParams, $window, CurrentUser, CSRF, Core, InvoicedConfig) {
        Core.setTitle('2FA Verification Needed');

        $('html').addClass('gray-bg');

        $scope.baseUrl = InvoicedConfig.baseUrl;
        $scope.verify = verify;
        $scope.sms = sms;

        function sms() {
            $scope.sending = true;
            $scope.error = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.requestSms2FA(
                    {},
                    function () {
                        $scope.sending = false;
                        $scope.sentSms = true;
                    },
                    function (result) {
                        $scope.sending = false;
                        $scope.error = result.data;
                    },
                );
            });
        }

        function verify(token) {
            $scope.verifying = true;
            $scope.error = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.verify2FA(
                    {
                        token: token,
                    },
                    function (result) {
                        $scope.verifying = false;
                        if (result && result.redirect_to) {
                            $window.location = result.redirect_to;
                        } else {
                            $state.go('index');
                        }
                    },
                    function (result) {
                        $scope.verifying = false;
                        $scope.error = result.data;
                    },
                );
            });
        }
    }
})();
