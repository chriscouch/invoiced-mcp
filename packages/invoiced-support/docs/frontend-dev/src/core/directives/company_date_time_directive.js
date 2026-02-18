(function () {
    'use strict';
    angular.module('app.core').directive('companyDateTime', companyDateTime);

    function companyDateTime() {
        return {
            restrict: 'E',
            template: '<span class="notranslate">{{date|formatCompanyDateTime}}</span>',
            scope: {
                date: '=',
            },
        };
    }
})();
