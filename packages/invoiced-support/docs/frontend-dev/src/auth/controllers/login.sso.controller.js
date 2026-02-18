(function () {
    'use strict';

    angular.module('app.auth').controller('LoginSsoController', LoginSsoController);

    LoginSsoController.$inject = ['$scope', '$stateParams', 'Core', 'InvoicedConfig', '$cookies'];

    function LoginSsoController($scope, $stateParams, Core, InvoicedConfig, $cookies) {
        $scope.ssoConnectUrl = InvoicedConfig.ssoConnectUrl;
        $scope.remember = true;
        $scope.email = $stateParams.email || $cookies.myEmail;
        if ($stateParams.error) {
            $scope.error = {};
            $scope.error.message = $stateParams.error;
            $scope.messageClass = $stateParams.is_warning ? 'alert-warning' : 'alert-danger';
        }

        $scope.applyCookie = function () {
            $cookies.myEmail = $scope.remember ? $scope.email : '';
        };

        Core.setTitle('Single Sign On');

        $('html').addClass('gray-bg');

        $scope.baseUrl = InvoicedConfig.baseUrl;
    }
})();
