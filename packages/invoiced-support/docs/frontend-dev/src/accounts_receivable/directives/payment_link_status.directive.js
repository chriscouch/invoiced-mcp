(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('paymentLinkStatus', paymentLinkStatus);

    function paymentLinkStatus() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="status-label" ng-if="paymentLink.status==\'active\'"><span class="label label-default">Active</span></a>' +
                '<a href="" class="status-label" ng-if="paymentLink.status==\'completed\'"><span class="label label-success">Completed</span></a>' +
                '<a href="" class="status-label" ng-if="paymentLink.status==\'deleted\'"><span class="label label-danger">Deleted</span></a>',
            scope: {
                paymentLink: '=',
            },
        };
    }
})();
