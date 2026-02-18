(function () {
    'use strict';

    angular.module('app.auth').controller('RegisterController', RegisterController);

    RegisterController.$inject = ['$scope', '$state', '$location', 'CurrentUser', 'CSRF', 'Core', 'InvoicedConfig'];

    function RegisterController($scope, $state, $location, CurrentUser, CSRF, Core, InvoicedConfig) {
        Core.setTitle('Register');

        $('html').addClass('gray-bg');

        let params = $location.search();
        $scope.emailPrefilled = false;
        if (params.email) {
            $scope.email = params.email;
            $scope.emailPrefilled = true;
        }

        if (params.first_name) {
            $scope.firstName = params.first_name;
        }

        if (params.last_name) {
            $scope.lastName = params.last_name;
        }

        $scope.baseUrl = InvoicedConfig.baseUrl;
        $scope.register = register;

        function register(firstName, lastName, email, password1, password2) {
            $scope.registering = true;
            $scope.error = false;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                CurrentUser.register(
                    {
                        first_name: firstName,
                        last_name: lastName,
                        email: email,
                        password: [password1, password2],
                    },
                    function () {
                        $scope.registering = false;
                        $state.go('index');
                    },
                    function (result) {
                        $scope.registering = false;
                        $scope.error = result.data;
                    },
                );
            });
        }
    }
})();
