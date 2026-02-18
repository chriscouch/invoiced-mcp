(function () {
    'use strict';

    angular.module('app.components').directive('columnArrangementHeaderCell', columnArrangementHeaderCell);

    function columnArrangementHeaderCell() {
        return {
            restrict: 'E',
            templateUrl: 'ui/views/column-arrangement-header-cell.html',
        };
    }
})();
