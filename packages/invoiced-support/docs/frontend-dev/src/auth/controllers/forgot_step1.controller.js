(function () {
    'use strict';

    angular.module('app.auth').controller('ForgotStep1Controller', ForgotStep1Controller);

    ForgotStep1Controller.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'CurrentUser',
        'CSRF',
        'Core',
        'InvoicedConfig',
    ];

    function ForgotStep1Controller($scope, $state, $stateParams, CurrentUser, CSRF, Core, InvoicedConfig) {
        if ($stateParams.email) {
            $scope.email = $stateParams.email;
        }

        Core.setTitle('Forgot Password');

        $('html').addClass('gray-bg');

        $scope.baseUrl = InvoicedConfig.baseUrl;
        $scope.submit = submit;

        function submit(email) {
            $scope.sending = true;
            $scope.error = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.forgotStep1(
                    {
                        email: email,
                    },
                    function () {
                        $scope.sending = false;
                        $scope.sent = true;
                    },
                    function (result) {
                        $scope.sending = false;
                        $scope.error = result.data;
                    },
                );
            });
        }
    }
})();
