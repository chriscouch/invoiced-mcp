(function () {
    'use strict';

    angular.module('app.core').filter('hasAllPermissions', hasAllPermissions);

    hasAllPermissions.$inject = ['Permission'];

    function hasAllPermissions(Permission) {
        return function (permissions) {
            return Permission.hasAllPermissions(permissions);
        };
    }
})();
