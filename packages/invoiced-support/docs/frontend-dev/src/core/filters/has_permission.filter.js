(function () {
    'use strict';

    angular.module('app.core').filter('hasPermission', hasPermission);

    hasPermission.$inject = ['Permission'];

    function hasPermission(Permission) {
        return function (permission) {
            return Permission.hasPermission(permission);
        };
    }
})();
