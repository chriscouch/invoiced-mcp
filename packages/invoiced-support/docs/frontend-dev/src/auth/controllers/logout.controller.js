(function () {
    'use strict';

    angular.module('app.auth').controller('LogoutController', LogoutController);

    LogoutController.$inject = ['$state', '$stateParams', '$timeout', '$rootScope', 'CurrentUser', 'CSRF', 'Core'];

    function LogoutController($state, $stateParams, $timeout, $rootScope, CurrentUser, CSRF, Core) {
        Core.showLoadingScreen('Goodbye!');

        let params = {};
        if ($stateParams.all) {
            params.all = true;
        }

        // retrieve a fresh CSRF token first
        CSRF(function () {
            CurrentUser.logout(
                params,
                function () {
                    // clear out any user/company caches
                    CurrentUser.clear();

                    $state.go('auth.login');

                    // The timeout is a hack to ensure the next controller
                    // is loaded.
                    $timeout(function () {
                        $rootScope.$broadcast('userSignedOut');
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $state.go('index');
                },
            );
        });
    }
})();
