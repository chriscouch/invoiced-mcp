/* globals inflection */
(function () {
    'use strict';

    angular.module('app.metadata').filter('metadataList', metadataList);

    function metadataList() {
        return function (obj) {
            let list = [];
            angular.forEach(obj.metadata, function (value, key) {
                if (value) {
                    let el = inflection.titleize(key).replace('-', ' ');
                    el += ': ' + value;
                    list.push(el);
                }
            });

            return list.join(', ');
        };
    }
})();
