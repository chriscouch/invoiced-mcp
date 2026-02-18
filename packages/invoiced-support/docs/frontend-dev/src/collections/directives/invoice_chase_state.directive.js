(function () {
    'use strict';

    angular.module('app.collections').directive('invoiceChaseState', invoiceChaseState);

    function invoiceChaseState() {
        return {
            restrict: 'E',
            templateUrl: 'collections/views/invoice-chase-state.html',
            scope: {
                state: '=',
            },
            controller: [
                '$scope',
                'InvoiceChaseState',
                function ($scope, InvoiceChaseState) {
                    $scope.chaseState = InvoiceChaseState.build(angular.copy($scope.state));
                    $scope.hasFailure = InvoiceChaseState.hasFailure;

                    $scope.$watch('state', function () {
                        $scope.chaseState = InvoiceChaseState.build(angular.copy($scope.state));
                    });
                },
            ],
        };
    }
})();
