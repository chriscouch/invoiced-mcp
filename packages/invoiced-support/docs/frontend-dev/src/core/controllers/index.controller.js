(function () {
    'use strict';

    angular.module('app.core').controller('IndexController', IndexController);

    IndexController.$inject = ['$state', '$cookies', '$location', 'Core'];

    function IndexController($state, $cookies, $location, Core) {
        Core.showLoadingScreen('Redirecting, hang on!');
        let redirect = $cookies.redirect;
        $cookies.redirect = '';
        // NOTE need to check if we are trying to redirect
        // to "/" to prevent a circular dependency. We ignore
        // that redirect and go to the dashboard instead.
        if (shouldRedirect(redirect)) {
            $location.url(redirect);
        } else {
            $state.go('manage.index');
        }
    }

    function shouldRedirect(endpoint) {
        return (
            endpoint &&
            endpoint !== '/' &&
            endpoint !== '/login' &&
            endpoint !== '/forgot' &&
            endpoint !== '/register' &&
            endpoint !== '/verify-2fa'
        );
    }
})();
