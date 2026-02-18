(function () {
    'use strict';

    angular.module('app.core').filter('searchObject', searchObject);

    searchObject.$inject = ['$filter'];

    function searchObject($filter) {
        return function search(value, search, properties) {
            return $filter('filter')(value, function (input) {
                return compareValue(input, search, properties);
            });
        };

        function compareValue(value, search, properties) {
            if (!search) {
                return true;
            }

            search = (search + '').toLowerCase().trim();
            for (let i in properties) {
                let propertyValue = (value[properties[i]] + '').toLowerCase().trim();
                if (propertyValue.indexOf(search) !== -1) {
                    return true;
                }
            }

            return false;
        }
    }
})();
