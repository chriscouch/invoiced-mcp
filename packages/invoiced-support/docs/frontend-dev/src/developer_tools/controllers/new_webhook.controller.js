(function () {
    'use strict';

    angular.module('app.developer_tools').controller('NewWebhookController', NewWebhookController);

    NewWebhookController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'Webhook',
        'InvoicedConfig',
        'webhook',
    ];

    function NewWebhookController($scope, $modalInstance, selectedCompany, Webhook, InvoicedConfig, webhook) {
        $scope.company = selectedCompany;
        $scope.eventTypes = InvoicedConfig.eventTypes;
        $scope.selectedEvents = {};
        $scope.enabledEvents = 'all';

        let excludedEvents = [];
        if (!webhook || webhook.events.indexOf('transaction.created') === -1) {
            excludedEvents.push('transaction.created');
            excludedEvents.push('transaction.updated');
            excludedEvents.push('transaction.deleted');
        }

        let i, j;
        for (i in excludedEvents) {
            j = $scope.eventTypes.indexOf(excludedEvents[i]);
            if (j !== -1) {
                $scope.eventTypes.splice(j, 1);
            }
        }

        if (webhook) {
            $scope.webhook = angular.copy(webhook);

            if (!angular.equals(webhook.events, ['*'])) {
                $scope.enabledEvents = 'some';
                for (i in webhook.events) {
                    $scope.selectedEvents[webhook.events[i]] = true;
                }
            } else {
                checkAll();
            }
        } else {
            $scope.webhook = {
                enabled: true,
            };
            checkAll();
        }

        $scope.noSelectedEvents = function () {
            for (let i in $scope.selectedEvents) {
                if ($scope.selectedEvents[i]) {
                    return false;
                }
            }

            return true;
        };

        $scope.atLeastOneSelectedEvent = function () {
            for (let i in $scope.selectedEvents) {
                if ($scope.selectedEvents[i]) {
                    return true;
                }
            }

            return false;
        };

        $scope.checkAll = checkAll;
        $scope.uncheckAll = uncheckAll;

        $scope.save = function (webhook, enabledEvents, selectedEvents) {
            $scope.saving = true;
            $scope.error = null;

            let events = ['*'];
            if ($scope.enabledEvents === 'some') {
                events = [];
                angular.forEach(selectedEvents, function (selected, eventType) {
                    if (selected) {
                        for (let i in $scope.eventTypes) {
                            if ($scope.eventTypes[i] == eventType) {
                                events.push($scope.eventTypes[i]);
                                break;
                            }
                        }
                    }
                });
            }

            if (webhook.id) {
                Webhook.edit(
                    {
                        id: webhook.id,
                        include: 'secret',
                    },
                    {
                        url: webhook.url,
                        enabled: webhook.enabled,
                        events: events,
                    },
                    function (_webhook) {
                        $scope.saving = false;
                        $modalInstance.close(_webhook);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                Webhook.create(
                    {
                        include: 'secret',
                    },
                    {
                        url: webhook.url,
                        enabled: webhook.enabled,
                        events: events,
                    },
                    function (_webhook) {
                        $scope.saving = false;
                        $modalInstance.close(_webhook);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function checkAll() {
            for (let i in $scope.eventTypes) {
                $scope.selectedEvents[$scope.eventTypes[i]] = true;
            }
        }

        function uncheckAll() {
            for (let i in $scope.eventTypes) {
                $scope.selectedEvents[$scope.eventTypes[i]] = false;
            }
        }
    }
})();
