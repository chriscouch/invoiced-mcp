(function () {
    'use strict';

    angular.module('app.themes').directive('themeDocumentType', themeDocumentType);

    function themeDocumentType() {
        return {
            restrict: 'E',
            template:
                '<span class="label label-info" ng-show="documentType==\'credit_note\'">Credit Note</span>' +
                '<span class="label label-warning" ng-show="documentType==\'estimate\'">Estimate</span>' +
                '<span class="label label-primary" ng-show="documentType==\'invoice\'">Invoice</span>' +
                '<span class="label label-success" ng-show="documentType==\'receipt\'">Receipt</span>' +
                '<span class="label label-danger" ng-show="documentType==\'statement\'">Statement</span>',
            scope: {
                documentType: '=',
            },
        };
    }
})();
