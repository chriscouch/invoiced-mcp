(function () {
    'use strict';

    angular.module('app.auth').controller('LoginModalController', LoginModalController);

    LoginModalController.$inject = ['$scope', '$modalInstance', 'InvoicedConfig', 'CSRF', 'CurrentUser'];

    function LoginModalController($scope, $modalInstance, InvoicedConfig, CSRF, CurrentUser) {
        $scope.baseUrl = InvoicedConfig.baseUrl;
        $scope.login = login;
        $scope.remember = true;
        $scope.googleSignInUrl = InvoicedConfig.baseUrl + '/auth/google';

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
                    function () {
                        $scope.signingIn = false;
                        $modalInstance.close();
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
