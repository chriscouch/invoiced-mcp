(function () {
    'use strict';

    angular.module('app.auth').controller('ForgotStep2Controller', ForgotStep2Controller);

    ForgotStep2Controller.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        'CurrentUser',
        'CSRF',
        'Core',
        'InvoicedConfig',
    ];

    function ForgotStep2Controller($scope, $state, $stateParams, CurrentUser, CSRF, Core, InvoicedConfig) {
        Core.setTitle('Change Password');

        $('html').addClass('gray-bg');

        $scope.baseUrl = InvoicedConfig.baseUrl;
        $scope.submit = submit;

        function submit(password1, password2) {
            $scope.saving = true;
            $scope.error = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.forgotStep2(
                    {
                        token: $stateParams.token,
                    },
                    {
                        password: [password1, password2],
                    },
                    function () {
                        $scope.saving = false;
                        $scope.changed = true;
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            });
        }
    }
})();
