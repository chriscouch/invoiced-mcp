(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('ageBubble', ageBubble);

    function ageBubble() {
        return {
            restrict: 'E',
            template:
                "<div class=\"invoice-age len-{{len}}\" ng-class=\"{'past-due':pastDue,'due-soon':dueSoon,'future':futureInvoice}\">" +
                '<a href="" tooltip="{{ageDays}}, {{status}}" tooltip-placement="right">{{age}}</a>' +
                '</div>',
            scope: {
                age: '=',
                pastDue: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.ageDays = $scope.age + ' day' + ($scope.age != 1 ? 's' : '') + ' old';

                    // determine status
                    $scope.dueSoon = false;
                    $scope.futureInvoice = $scope.age < 0;

                    $scope.len = $scope.age.toString().length;

                    $scope.status = 'Current';
                    if ($scope.pastDue) {
                        $scope.status = 'Past Due';
                    } else if ($scope.futureInvoice) {
                        $scope.status = 'Future Invoice';
                    } else if ($scope.dueSoon) {
                        $scope.status = 'Due Soon';
                    }
                },
            ],
        };
    }
})();
