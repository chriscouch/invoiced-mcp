(function () {
    'use strict';

    angular.module('app.core').factory('Permission', Permission);

    Permission.$inject = ['selectedCompany'];

    /**
     * Service for determining permissions for certain actions and views.
     *
     * @param selectedCompany
     * @returns {{hasAllPermissions: (function(*=): boolean), hasSomePermissions: (function(*=): boolean), hasPermission: (function(*=): boolean)}}
     * @constructor
     */
    function Permission(selectedCompany) {
        let service = {
            hasPermission: hasPermission,
            hasAllPermissions: hasAllPermissions,
            hasSomePermissions: hasSomePermissions,
        };

        return service;

        /**
         * Determines if user has given permission.
         *
         * @param {string} permission   The permission to be tested.
         * @returns {boolean}           True if the user has permission, False if not.
         */
        function hasPermission(permission) {
            return selectedCompany.permissions.indexOf(permission) !== -1;
        }

        /**
         * Determines if the user has ALL of the given permissions.
         *
         * @param {array} permissions   The array of permissions to be tested.
         * @returns {boolean}           True if the user has all permissions, False if not.
         */
        function hasAllPermissions(permissions) {
            let hasPermissions = true;
            angular.forEach(permissions, function (permission) {
                hasPermissions = hasPermissions && selectedCompany.permissions.indexOf(permission) !== -1;
            });
            return hasPermissions;
        }

        /**
         * Determines if the user has at least ONE of the given permissions.
         *
         * @param {array} permissions   The array of permissions to be tested.
         * @returns {boolean}           True if the user has one or more permissions, False if not.
         */
        function hasSomePermissions(permissions) {
            let hasPermission = false;
            angular.forEach(permissions, function (permission) {
                if (selectedCompany.permissions.indexOf(permission) !== -1) {
                    hasPermission = true;
                }
            });
            return hasPermission;
        }
    }
})();
