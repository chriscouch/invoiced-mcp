(function () {
    'use strict';

    angular.module('app.core').filter('userName', userName);

    function userName() {
        return function (val) {
            if (!val) {
                return '';
            }

            return $.trim(val.first_name + ' ' + val.last_name);
        };
    }
})();
