(function () {
    'use strict';
    angular.module('app.core').directive('companyDate', companyDate);

    function companyDate() {
        return {
            restrict: 'E',
            template: '<span class="notranslate">{{date|formatCompanyDate}}</span>',
            scope: {
                date: '=',
            },
        };
    }
})();
