/* globals inflection */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').filter('itemTypeName', itemTypeName);

    function itemTypeName() {
        return function (key) {
            return inflection.titleize(key).replace('-', ' ');
        };
    }
})();
