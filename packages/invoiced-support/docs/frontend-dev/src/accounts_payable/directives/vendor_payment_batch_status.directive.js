(function () {
    'use strict';

    angular.module('app.accounts_payable').directive('vendorPaymentBatchStatus', vendorPaymentBatchStatus);

    function vendorPaymentBatchStatus() {
        return {
            restrict: 'E',
            template:
                '<span ng-if="paymentBatch.status==\'Created\'"><span class="label label-default">Created</span></span>' +
                '<span ng-if="paymentBatch.status==\'Processing\'"><span class="label label-warning">Processing</span></span>' +
                '<span ng-if="paymentBatch.status==\'Finished\'"><span class="label label-success">Finished</span></span>' +
                '<span ng-if="paymentBatch.status==\'Voided\'"><span class="label label-danger">Voided</span></span>',
            scope: {
                paymentBatch: '=',
            },
        };
    }
})();
