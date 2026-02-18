(function () {
    'use strict';

    angular.module('app.core').filter('hasSomePermissions', hasSomePermissions);

    hasSomePermissions.$inject = ['Permission'];

    function hasSomePermissions(Permission) {
        return function (permissions) {
            return Permission.hasSomePermissions(permissions);
        };
    }
})();
