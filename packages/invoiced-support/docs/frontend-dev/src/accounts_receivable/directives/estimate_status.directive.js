(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('estimateStatus', estimateStatus);

    function estimateStatus() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="status-label" tooltip="The estimate has not been added to the customer\'s account yet." ng-if="estimate.status==\'draft\'"><span class="label label-default">Draft</span></a>' +
                '<a href="" class="status-label" tooltip="The estimate is outstanding but not sent to the customer yet." ng-if="estimate.status==\'not_sent\'"><span class="label label-warning">Not Sent</span></a>' +
                '<a href="" class="status-label" tooltip="The estimate is outstanding and has been sent to the customer. We\'re just waiting for them to view it." ng-if="estimate.status==\'sent\'"><span class="label label-primary">Sent</span></a>' +
                '<a href="" class="status-label" tooltip="The estimate is outstanding and the customer has viewed it." ng-if="estimate.status==\'viewed\'"><span class="label label-info">Viewed</span></a>' +
                '<a href="" class="status-label" tooltip="The estimate has expired." ng-if="estimate.status==\'expired\'"><span class="label label-danger">Expired</span></a>' +
                '<a href="" class="status-label" tooltip="The customer declind the estimate." ng-if="estimate.status==\'declined\'"><span class="label label-danger">Declined</span></a>' +
                '<a href="" class="status-label" tooltip="The estimate has been voided." ng-if="estimate.status==\'voided\'"><span class="label label-danger">Voided</span></a>' +
                '<a href="" class="status-label" tooltip="The customer approved the estimate." ng-if="estimate.status==\'approved\'"><span class="label label-success">Approved</span></a>' +
                '<a href="" class="status-label" tooltip="The customer has been invoiced for this estimate." ng-if="estimate.status==\'invoiced\'"><span class="label label-success">Invoiced</span></a>',
            scope: {
                estimate: '=',
            },
        };
    }
})();
