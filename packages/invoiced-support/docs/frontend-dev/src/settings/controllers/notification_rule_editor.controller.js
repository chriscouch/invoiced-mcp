(function () {
    'use strict';

    angular.module('app.settings').controller('NotificationRuleEditorController', NotificationRuleEditorController);

    NotificationRuleEditorController.$inject = [
        '$scope',
        '$modalInstance',
        '$translate',
        'selectedCompany',
        'CurrentUser',
        'Notification',
        'rule',
        'slackConnected',
        'slackChannel',
        'notifications',
        'Feature',
    ];

    function NotificationRuleEditorController(
        $scope,
        $modalInstance,
        $translate,
        selectedCompany,
        CurrentUser,
        Notification,
        rule,
        slackConnected,
        slackChannel,
        notifications,
        Feature,
    ) {
        if (rule) {
            $scope.rule = angular.copy(rule);
        } else {
            $scope.rule = {};
        }

        if (!$scope.rule.id) {
            $scope.rule = angular.extend(
                {
                    event: '',
                    // from: 'anyone',
                    medium: 'email',
                    enabled: true,
                },
                $scope.rule,
            );
        }

        $scope.user = CurrentUser.profile;
        $scope.slackConnected = slackConnected;
        $scope.slackChannel = slackChannel;
        let hasEstimates = Feature.hasFeature('estimates');

        let events = [
            {
                id: '',
                name: 'Please select one',
            },
            // Customers
            {
                id: 'customer.created',
                group: 'Customers',
            },
            {
                id: 'customer.updated',
                group: 'Customers',
            },
            {
                id: 'customer.deleted',
                group: 'Customers',
            },
            // Invoices
            {
                id: 'invoice.created',
                group: 'Invoices',
            },
            {
                id: 'invoice.updated',
                group: 'Invoices',
            },
            {
                id: 'invoice.viewed',
                group: 'Invoices',
            },
            {
                id: 'invoice.payment_expected',
                group: 'Invoices',
            },
            {
                id: 'invoice.paid',
                group: 'Invoices',
            },
            {
                id: 'invoice.deleted',
                group: 'Invoices',
            },
            // Subscriptions
            {
                id: 'subscription.created',
                group: 'Subscriptions',
            },
            {
                id: 'subscription.updated',
                group: 'Subscriptions',
            },
            {
                id: 'subscription.deleted',
                group: 'Subscriptions',
            },
            // Payments
            {
                id: 'payment.created',
                group: 'Payments',
            },
            {
                id: 'payment.updated',
                group: 'Payments',
            },
            {
                id: 'payment.deleted',
                group: 'Payments',
            },
            {
                id: 'charge.failed',
                group: 'Payments',
            },
        ];

        if (hasEstimates) {
            events.push({
                id: 'estimate.created',
                group: 'Estimates',
            });
            events.push({
                id: 'estimate.updated',
                group: 'Estimates',
            });
            events.push({
                id: 'estimate.viewed',
                group: 'Estimates',
            });
            events.push({
                id: 'estimate.approved',
                group: 'Estimates',
            });
            events.push({
                id: 'estimate.deleted',
                group: 'Estimates',
            });
        }

        $scope.events = getAvailableEvents();

        $scope.save = function (rule) {
            $scope.saving = true;
            $scope.error = false;
            if (rule.id) {
                edit(rule);
            } else {
                add(rule);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function edit(rule) {
            Notification.edit(
                {
                    id: rule.id,
                },
                {
                    event: rule.event,
                    medium: rule.medium,
                    enabled: true,
                },
                function (_rule) {
                    $scope.saving = false;
                    $modalInstance.close(_rule);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function add(rule) {
            // add user ID if medium is not slack
            if (rule.medium !== 'slack') {
                rule.user_id = CurrentUser.profile.id;
            }

            Notification.create(
                rule,
                function (_rule) {
                    $scope.saving = false;
                    $modalInstance.close(_rule);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function getAvailableEvents() {
            let selectedEvents = notifications.reduce(function (selected, notification) {
                // Ignore the current selection;
                // it should be included in the list of selectable events.
                if (rule && rule.event === notification.event) {
                    return selected;
                }

                // Ignore notifications w/ conditions;
                // notifications w/ conditions should be treated as unique events.
                if (notification.conditions && notification.conditions.length > 0) {
                    return selected;
                }

                selected.push(notification.event);
                return selected;
            }, []);

            return events.filter(function (event) {
                event.name = typeof event.name !== 'undefined' ? event.name : $translate.instant('events.' + event.id);

                return selectedEvents.indexOf(event.id) === -1;
            });
        }
    }
})();
