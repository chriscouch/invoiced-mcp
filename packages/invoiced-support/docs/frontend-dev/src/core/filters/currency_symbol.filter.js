(function () {
    'use strict';

    angular.module('app.core').filter('currencySymbol', currencySymbol);

    currencySymbol.$inject = ['Money'];

    function currencySymbol(Money) {
        return function (currency) {
            return Money.currencySymbol(currency);
        };
    }
})();
