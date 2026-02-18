(function () {
    'use strict';

    angular
        .module('app.developer_tools')
        .controller('BrowseWebhookAttemptsController', BrowseWebhookAttemptsController);

    BrowseWebhookAttemptsController.$inject = ['$scope', '$state', '$controller', 'WebhookAttempt', 'Core'];

    function BrowseWebhookAttemptsController($scope, $state, $controller, WebhookAttempt, Core) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = WebhookAttempt;
        $scope.modelTitleSingular = 'Webhoook Attempt';
        $scope.modelTitlePlural = 'Webhook Attempts';

        $scope.attempts = [];

        //
        // Methods
        //

        $scope.postFindAll = function (attempts) {
            angular.forEach(attempts, function (attempt) {
                // Determine last attempt timestamp, status code, and any error reason
                attempt.last_status_code = null;
                attempt.last_attempt = null;
                attempt.error_reason = null;
                if (attempt.attempts.length > 0) {
                    let lastCall = attempt.attempts[attempt.attempts.length - 1];
                    if (typeof lastCall.status_code !== 'undefined') {
                        attempt.last_status_code = lastCall.status_code;
                    } else if (typeof lastCall.error !== 'undefined') {
                        attempt.error_reason = lastCall.error;
                    }
                    attempt.last_attempt = lastCall.timestamp;
                }

                // Build a status reason
                if (attempt.next_attempt) {
                    attempt.status = 'retry_pending';
                } else if (attempt.last_status_code > 100 && attempt.last_status_code < 300) {
                    attempt.status = 'succeeded';
                } else if (attempt.last_status_code >= 300 || attempt.error_reason) {
                    attempt.status = 'failed';
                } else {
                    attempt.status = 'not_attempted';
                }
            });
            $scope.attempts = attempts;
        };

        $scope.goEvent = function (id) {
            $state.go('manage.event.view', {
                id: id,
            });
        };

        $scope.noResults = function () {
            return $scope.attempts.length === 0;
        };

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Webhook Attempts');
    }
})();
