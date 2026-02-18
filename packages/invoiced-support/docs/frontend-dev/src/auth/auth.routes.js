(function () {
    'use strict';

    angular.module('app.auth').config(routes);

    routes.$inject = ['$stateProvider', '$sceDelegateProvider', 'InvoicedConfig'];

    function routes($stateProvider, $sceDelegateProvider, InvoicedConfig) {
        //this is used to allow ngrock testing
        $sceDelegateProvider.resourceUrlWhitelist(['self', InvoicedConfig.ssoConnectUrl]);

        $stateProvider
            .state('manage.account', {
                url: '/account?t',
                templateUrl: 'auth/views/account.html',
                controller: 'AccountController',
            })

            .state('auth', {
                abstract: true,
                url: '',
                template: '<ui-view/>',
            })
            .state('auth.register', {
                url: '/register',
                templateUrl: 'auth/views/register.html',
                controller: 'RegisterController',
            })
            .state('auth.signup', {
                url: '/signup',
                controller: [
                    '$state',
                    function ($state) {
                        $state.go('auth.register');
                    },
                ],
            })
            .state('auth.login', {
                url: '/login',
                templateUrl: 'auth/views/login.html',
                controller: 'LoginController',
            })
            .state('auth.verify_2fa', {
                url: '/verify-2fa',
                templateUrl: 'auth/views/verify-2fa.html',
                controller: 'Verify2FAController',
            })
            .state('auth.logout', {
                url: '/logout?all',
                controller: 'LogoutController',
            })
            .state('auth.forgot', {
                url: '/forgot?email',
                templateUrl: 'auth/views/forgot-step1.html',
                controller: 'ForgotStep1Controller',
            })
            .state('auth.forgot_step2', {
                url: '/forgot/:token?email',
                templateUrl: 'auth/views/forgot-step2.html',
                controller: 'ForgotStep2Controller',
            })
            .state('auth.sso', {
                url: '/login/sso?email&error&is_warning',
                templateUrl: 'auth/views/login-sso.html',
                controller: 'LoginSsoController',
            });
    }
})();
