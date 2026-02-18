/* globals moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('invoiceAge', invoiceAge);

    function invoiceAge() {
        return {
            restrict: 'E',
            template:
                "<div class=\"invoice-age len-{{len}}\" ng-class=\"{'past-due':pastDue,'due-soon':dueSoon,'future':futureInvoice}\" ng-if=\"!invoice.paid&&!invoice.closed&&!invoice.draft&&invoice.status!='voided'\">" +
                '<a href="" tooltip="{{ageDays}}, {{status}}" tooltip-placement="right">{{age}}</a>' +
                '</div>' +
                '<div class="invoice-draft" ng-if="invoice.draft&&invoice.status!=\'voided\'">' +
                '<a href="" tooltip="Draft" tooltip-placement="right"><span class="fad fa-edit"></span></a>' +
                '</div>' +
                '<div class="invoice-bad-debt" ng-if="!invoice.paid&&invoice.closed&&!invoice.draft&&invoice.status!=\'voided\'">' +
                '<a href="" tooltip="Bad Debt" tooltip-placement="right"><span class="fad fa-times"></span></a>' +
                '</div>' +
                '<div class="invoice-voided" ng-if="invoice.status==\'voided\'">' +
                '<a href="" tooltip="Voided" tooltip-placement="right"><span class="fad fa-minus-circle"></span></a>' +
                '</div>' +
                '<div class="invoice-paid" ng-if="invoice.paid&&!invoice.draft&&invoice.status!=\'voided\'">' +
                '<a href="" tooltip="Paid In Full" tooltip-placement="right"><span class="fad fa-check"></span></a>' +
                '</div>',
            scope: {
                invoice: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    let age = moment().diff($scope.invoice.date, 'days');
                    $scope.age = age;
                    $scope.ageDays = age + ' day' + (age != 1 ? 's' : '') + ' old';

                    let dueAgo = $scope.invoice.due_date ? moment().diff($scope.invoice.due_date, 'days') : false;
                    $scope.pastDue = $scope.invoice.status == 'past_due';
                    $scope.dueSoon = dueAgo !== false && dueAgo >= -7 && dueAgo < 0;
                    $scope.futureInvoice = age < 0;

                    $scope.len = age.toString().length;

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
