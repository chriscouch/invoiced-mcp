(function () {
    'use strict';
    angular.module('app.inboxes').directive('arInboxMigration', arInboxMigration);

    function arInboxMigration() {
        return {
            restrict: 'E',
            template:
                '<div class="alert alert-info" ng-if="!(\'inboxes\'|hasFeature) && (\'ar_inbox\'|hasFeature)">' +
                '<p><span class="label label-default">New</span> Turn on A/R Inbox to gain new email capabilities, a shared mailbox for all incoming and outgoing customer emails, and workflow tools designed for accounts receivable and billing communications.</p><hr/>' +
                '<p>' +
                '<a href="" class="btn btn-primary" ng-click="migrate()">Enable A/R Inbox</a>' +
                '<a href="https://docs.invoiced.com/accounts-receivable/ar-inbox" class="btn btn-link" target="_blank">Learn More</a>' +
                '</p>' +
                '</div>',
            controller: [
                '$scope',
                '$window',
                'Inbox',
                'Core',
                function ($scope, $window, Inbox, Core) {
                    $scope.migrate = function () {
                        Inbox.migrate(
                            function () {
                                $window.location.reload();
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    };
                },
            ],
        };
    }
})();
