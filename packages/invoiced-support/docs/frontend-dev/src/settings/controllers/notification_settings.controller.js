/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('NotificationSettingsController', NotificationSettingsController);

    NotificationSettingsController.$inject = [
        '$scope',
        '$modal',
        '$stateParams',
        '$state',
        'Notification',
        'Core',
        'Integration',
        'CurrentUser',
        'Member',
        '$translate',
        'UserNotificationSetting',
        'Permission',
        'Feature',
        'CompanyNotificationSetting',
        'selectedCompany',
        'InvoicedConfig',
    ];

    function NotificationSettingsController(
        $scope,
        $modal,
        $stateParams,
        $state,
        Notification,
        Core,
        Integration,
        CurrentUser,
        Member,
        $translate,
        UserNotificationSetting,
        Permission,
        Feature,
        CompanyNotificationSetting,
        selectedCompany,
        InvoicedConfig,
    ) {
        //
        // Models
        //

        $scope.savingUserNotifications = false;

        $scope.myNotificationsNew = generateNotificationSettings();
        $scope.companyNotifications = angular.copy($scope.myNotificationsNew);

        $scope.newNotifications = false;
        $scope.hideMigrateSettings = false;
        $scope.user = angular.copy(CurrentUser.profile);
        $scope.current_user_email = $scope.user.email;

        //
        // Settings
        //

        $scope.myNotifications = [];
        $scope.slackNotifications = [];

        $scope.loading = 0;
        $scope.saving = {};
        $scope.upgrading = false;
        $scope.deleting = {};
        $scope.converting = 0;

        $scope.canEditDefaults = Permission.hasPermission('business.admin');
        $scope.canManageOwnSettings = Permission.hasPermission('notifications.edit');
        $scope.enableIndividualV2Rollout = Feature.hasFeature('notifications_v2_individual');

        //
        // Methods
        //

        $scope.convert = function () {
            $scope.converting = 1;
            CompanyNotificationSetting.convert(
                function () {
                    $scope.converting = 0;
                    selectedCompany.features.push('notifications_v2_default');
                    $scope.hideMigrateSettings = true;
                    Core.flashMessage('All users have been converted to the new notification system.', 'success');
                },
                function (result) {
                    $scope.converting = 0;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.changeIndividual = function () {
            Feature.edit(
                {
                    id: 'notifications_v2_individual',
                },
                {
                    enabled: !$scope.enableIndividualV2Rollout,
                },
                function (data) {
                    Core.flashMessage($translate.instant('notifications.saved'), 'success');
                    $scope.enableIndividualV2Rollout = data.enabled;
                },
                function (result) {
                    $scope.error = result.data;
                },
            );
        };

        $scope.addRule = function (medium) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/notification-rule-editor.html',
                controller: 'NotificationRuleEditorController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    rule: function () {
                        return {
                            medium: medium,
                        };
                    },
                    slackConnected: function () {
                        return $scope.slackConnected;
                    },
                    slackChannel: function () {
                        return $scope.slackChannel;
                    },
                    notifications: getNotifications,
                },
            });

            modalInstance.result.then(
                function (rule) {
                    Core.flashMessage('Your notification rule has been added.', 'success');
                    let k = rule.medium === 'slack' ? 'slackNotifications' : 'myNotifications';
                    $scope[k].push(rule);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.editRule = function (rule) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/notification-rule-editor.html',
                controller: 'NotificationRuleEditorController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    rule: function () {
                        return rule;
                    },
                    slackConnected: function () {
                        return $scope.slackConnected;
                    },
                    slackChannel: function () {
                        return $scope.slackChannel;
                    },
                    notifications: getNotifications,
                },
            });

            modalInstance.result.then(
                function (_rule) {
                    Core.flashMessage('Your notification rule has been updated.', 'success');
                    angular.extend(rule, _rule);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.enableRule = function (rule) {
            $scope.saving[rule.id] = true;

            Notification.edit(
                {
                    id: rule.id,
                },
                {
                    enabled: true,
                },
                function () {
                    $scope.saving[rule.id] = false;
                    rule.enabled = true;
                },
                function (result) {
                    $scope.saving[rule.id] = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.disableRule = function (rule) {
            $scope.saving[rule.id] = true;

            Notification.edit(
                {
                    id: rule.id,
                },
                {
                    enabled: false,
                },
                function () {
                    $scope.saving[rule.id] = false;
                    rule.enabled = false;
                },
                function (result) {
                    $scope.saving[rule.id] = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.deleteRule = function (rule) {
            vex.dialog.confirm({
                message: 'Are you sure you want to remove this notification?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[rule.id] = true;

                        Notification.delete(
                            {
                                id: rule.id,
                            },
                            function () {
                                $scope.deleting[rule.id] = false;

                                // remove the notification locally
                                let k = rule.medium === 'slack' ? 'slackNotifications' : 'myNotifications';
                                for (let i in $scope[k]) {
                                    if ($scope[k][i].id == rule.id) {
                                        $scope[k].splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deleting[rule.id] = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.upgradeNotifications = function () {
            $scope.upgrading = true;
            $scope.saveMember = $scope.saveMember($scope.member, 'notifications', true, function (member) {
                $scope.member = member;
                $scope.upgrading = false;
                $scope.tab = 'user_notification_settings';
                $scope.newNotifications = $scope.member && $scope.member.notifications;
                loadUserNotificationSettings();
            });
        };

        $scope.saveMember = function (member, key, value, callback) {
            if (!callback) {
                callback = function () {};
            }
            let params = {};
            params[key] = value;
            Member.setUpdateFrequency(
                {
                    id: member.id,
                },
                params,
                function (member) {
                    Core.flashMessage($translate.instant('notifications.saved'), 'success');
                    callback(member);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.saveUserNotification = function (id, frequency) {
            $scope.savingUserNotifications = true;
            let params = {
                settings: [
                    {
                        notification_type: id,
                        frequency: frequency,
                    },
                ],
            };
            UserNotificationSetting.setAll(
                params,
                function () {
                    Core.flashMessage($translate.instant('notifications.saved'), 'success');
                    $scope.savingUserNotifications = false;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.saveCompanyNotification = function (id, frequency) {
            let params = {
                settings: [
                    {
                        notification_type: id,
                        frequency: frequency,
                    },
                ],
            };
            CompanyNotificationSetting.setAll(
                params,
                function () {
                    Core.flashMessage($translate.instant('notifications.saved'), 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        //
        // Initialization
        //

        Core.setTitle('Notification Settings');
        load();

        function generateNotificationSettings() {
            // Remove the notifications that the user does not have access to
            // and group into categories
            let notificationsByCategory = {};
            angular.forEach(InvoicedConfig.notifications, function (notification) {
                if (
                    typeof notification.allFeatures !== 'undefined' &&
                    !Feature.hasAllFeatures(notification.allFeatures)
                ) {
                    return;
                }

                if (
                    typeof notification.someFeatures !== 'undefined' &&
                    !Feature.hasSomeFeatures(notification.someFeatures)
                ) {
                    return;
                }

                if (typeof notification.feature !== 'undefined' && !Feature.hasFeature(notification.feature)) {
                    return;
                }

                if (typeof notificationsByCategory[notification.category] === 'undefined') {
                    notificationsByCategory[notification.category] = {
                        name: notification.category,
                        notifications: [],
                    };
                }

                notificationsByCategory[notification.category].notifications.push(angular.copy(notification));
            });

            return notificationsByCategory;
        }

        function load() {
            $scope.loading++;
            Member.current(
                function (member) {
                    $scope.loading--;
                    $scope.member = member;

                    $scope.newNotifications = $scope.member && $scope.member.notifications;

                    if ($stateParams.tab) {
                        $scope.tab = $stateParams.tab;
                    } else {
                        if ($scope.newNotifications) {
                            $scope.tab = Permission.hasPermission('notifications.edit')
                                ? 'user_notification_settings'
                                : 'slack';
                        } else {
                            $scope.tab = 'me';
                        }
                    }

                    loadSlack();
                    if ($scope.newNotifications) {
                        loadUserNotificationSettings();
                    } else {
                        loadNotifications();
                    }
                },
                function () {
                    $scope.loading--;
                },
            );

            if ($scope.canEditDefaults) {
                loadCompanyNotificationSettings();
            }
        }

        function loadNotifications() {
            $scope.loading++;
            Notification.findAll(
                {
                    'filter[user_id]': CurrentUser.profile.id,
                    paginate: 'none',
                },
                function (notifications) {
                    $scope.myNotifications = notifications;
                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadSlack() {
            $scope.loading++;

            Integration.retrieve(
                {
                    id: 'slack',
                },
                function (result) {
                    //if webhook channel is not specified - this means slack is migrated
                    $scope.slackConnected = result.connected && result.extra.webhook_channel;
                    $scope.slackAccountName = result.name;
                    $scope.slackChannel = result.extra.webhook_channel;
                    $scope.slackConfigUrl = result.extra.webhook_config_url;
                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );

            $scope.loading++;
            Notification.findAll(
                {
                    'filter[medium]': 'slack',
                    paginate: 'none',
                },
                function (notifications) {
                    $scope.slackNotifications = notifications;
                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadUserNotificationSettings() {
            $scope.loading++;
            UserNotificationSetting.findAll(
                { paginate: 'none' },
                function (data) {
                    parseNotificationSettings($scope.myNotificationsNew, data);
                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
        function loadCompanyNotificationSettings() {
            $scope.loading++;
            CompanyNotificationSetting.findAll(
                { paginate: 'none' },
                function (data) {
                    parseNotificationSettings($scope.companyNotifications, data);
                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function getNotifications() {
            if ($scope.tab === 'slack') {
                return $scope.slackNotifications;
            }

            return $scope.myNotifications;
        }

        function parseNotificationSettings(source, data) {
            angular.forEach(data, function (notification) {
                angular.forEach(source, function (sourceCategory) {
                    angular.forEach(sourceCategory.notifications, function (notification2) {
                        if (notification.notification_type === notification2.notification_type) {
                            notification2.frequency = notification.frequency;
                            return false;
                        }
                    });
                });
            });
        }
    }
})();
