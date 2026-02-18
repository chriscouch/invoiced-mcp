(function () {
    'use strict';

    angular.module('app.subscriptions').directive('subscriptionStatus', subscriptionStatus);

    function subscriptionStatus() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="status-label" tooltip="The subscription is currently in the trial period." ng-if="subscription.status==\'not_started\'"><span class="label label-warning">Trialing</span></a>' +
                '<a href="" class="status-label" tooltip="The subscription is running and current." ng-if="subscription.status==\'active\'"><span class="label label-success">Active</span></a>' +
                '<a href="" class="status-label" tooltip="The subscription is past due. It\'s time to contact the customer." ng-if="subscription.status==\'past_due\'"><span class="label label-danger">Past Due</span></a>' +
                '<a href="" class="status-label" tooltip="The subscription is paused and needs to be resumed to continue billing." ng-if="subscription.status==\'paused\'"><span class="label label-warning">Paused</span></a>' +
                '<a href="" class="status-label" tooltip="The current contract term is finished and needs to be renewed to continue billing." ng-if="subscription.status==\'pending_renewal\'"><span class="label label-warning">Pending Renewal</span></a>' +
                '<a href="" class="status-label" tooltip="The subscription has completed all billing cycles." ng-if="subscription.status==\'finished\'"><span class="label label-success">Finished</span></a>' +
                '<a href="" class="status-label" tooltip="The subscription has been canceled." ng-if="subscription.status==\'canceled\'"><span class="label label-danger">Canceled</span></a>',
            scope: {
                subscription: '=',
            },
        };
    }
})();
