(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('invoiceStatus', invoiceStatus);

    function invoiceStatus() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="status-label" tooltip="The invoice has not been added to the customer\'s account yet." ng-if="invoice.status==\'draft\'"><span class="label label-default">Draft</span></a>' +
                '<a href="" class="status-label" tooltip="The invoice is outstanding but has not been sent to the customer yet" ng-if="invoice.status==\'not_sent\'"><span class="label label-warning">Not Sent</span></a>' +
                '<a href="" class="status-label" tooltip="The invoice is outstanding and has been sent to the customer. We\'re just waiting for them to view it." ng-if="invoice.status==\'sent\'"><span class="label label-primary">Sent</span></a>' +
                '<a href="" class="status-label" tooltip="The invoice is outstanding and the customer has viewed it. You are close to getting paid!" ng-if="invoice.status==\'viewed\'"><span class="label label-info">Viewed</span></a>' +
                '<a href="" class="status-label" tooltip="The invoice is past due. It\'s time to contact the customer." ng-if="invoice.status==\'past_due\'"><span class="label label-danger">Past Due</span></a>' +
                '<a href="" class="status-label" tooltip="The invoice was closed before paid in full." ng-if="invoice.status==\'bad_debt\'"><span class="label label-danger">Bad Debt</span></a>' +
                '<a href="" class="status-label" tooltip="The invoice was voided." ng-if="invoice.status==\'voided\'"><span class="label label-danger">Voided</span></a>' +
                '<a href="" class="status-label" tooltip="Payment on this invoice is pending." ng-if="invoice.status==\'pending\'"><span class="label label-warning">Pending</span></a>' +
                '<a href="" class="status-label" tooltip="Paid in full." ng-if="invoice.status==\'paid\'"><span class="label label-success">Paid</span></a>',
            scope: {
                invoice: '=',
            },
        };
    }
})();
