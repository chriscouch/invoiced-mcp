/* globals InvoicedConfig, vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('DeveloperSettingsController', DeveloperSettingsController);

    DeveloperSettingsController.$inject = [
        '$scope',
        '$modal',
        'selectedCompany',
        'Company',
        'ApiKey',
        'Webhook',
        'OAuthApplication',
        'Core',
        'Feature',
    ];

    function DeveloperSettingsController(
        $scope,
        $modal,
        selectedCompany,
        Company,
        ApiKey,
        Webhook,
        OAuthApplication,
        Core,
        Feature,
    ) {
        $scope.company = selectedCompany;

        $scope.isProduction = InvoicedConfig.environment !== 'sandbox';
        $scope.apiKeys = [];
        $scope.showApiKeySecret = {};
        $scope.deletingApiKey = {};
        $scope.invoicedJsPublishableKey = InvoicedConfig.paymentsPublishableKey;

        $scope.showWebhookSecret = {};
        $scope.webhooks = [];
        $scope.deletingWebhook = {};

        $scope.showOAuthApplicationSecret = {};
        $scope.oauthApplications = [];
        $scope.deletingOAuthApplication = {};

        $scope.newApiKeyModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'developer_tools/views/new-api-key.html',
                controller: 'NewApiKeyController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    model: function () {
                        return {
                            description: '',
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (apiKey) {
                    $scope.apiKeys.push(apiKey);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.removeApiKey = function (apiKey) {
            vex.dialog.confirm({
                message:
                    'Are you sure you want to delete this API Key?<br/>Any applications using this API Key will stop working. Please make sure that no applications are using this key first.',
                callback: function (result) {
                    if (result) {
                        $scope.deletingApiKey[apiKey.id] = true;

                        ApiKey.delete(
                            {
                                id: apiKey.id,
                            },
                            function () {
                                $scope.deletingApiKey[apiKey.id] = false;
                                for (let i in $scope.apiKeys) {
                                    if ($scope.apiKeys[i].id === apiKey.id) {
                                        $scope.apiKeys.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deletingApiKey[apiKey.id] = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.newWebhookModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'developer_tools/views/new-webhook.html',
                controller: 'NewWebhookController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    webhook: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (webhook) {
                    $scope.webhooks.push(webhook);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.editWebhook = function (webhook) {
            const modalInstance = $modal.open({
                templateUrl: 'developer_tools/views/new-webhook.html',
                controller: 'NewWebhookController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    webhook: function () {
                        return webhook;
                    },
                },
            });

            modalInstance.result.then(
                function (webhook2) {
                    angular.extend(webhook, webhook2);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteWebhook = function (webhook) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this webhook?',
                callback: function (result) {
                    if (result) {
                        $scope.deletingWebhook[webhook.id] = true;

                        Webhook.delete(
                            {
                                id: webhook.id,
                            },
                            function () {
                                $scope.deletingWebhook[webhook.id] = false;
                                for (let i in $scope.webhooks) {
                                    if ($scope.webhooks[i].id === webhook.id) {
                                        $scope.webhooks.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deletingWebhook[webhook.id] = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.newOAuthApplicationModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'developer_tools/views/new-oauth-application.html',
                controller: 'NewOAuthApplicationController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    application: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (application) {
                    $scope.oauthApplications.push(application);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.editOAuthApplication = function (application) {
            const modalInstance = $modal.open({
                templateUrl: 'developer_tools/views/new-oauth-application.html',
                controller: 'NewOAuthApplicationController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    application: function () {
                        return application;
                    },
                },
            });

            modalInstance.result.then(
                function (application2) {
                    angular.extend(application, application2);
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteOAuthApplication = function (application) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this application?',
                callback: function (result) {
                    if (result) {
                        $scope.deletingOAuthApplication[application.id] = true;

                        OAuthApplication.delete(
                            {
                                id: application.id,
                            },
                            function () {
                                $scope.deletingOAuthApplication[application.id] = false;
                                for (let i in $scope.oauthApplications) {
                                    if ($scope.oauthApplications[i].id === application.id) {
                                        $scope.oauthApplications.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deletingOAuthApplication[application.id] = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.clearData = function (company) {
            if (!company.test_mode) {
                return;
            }

            vex.dialog.confirm({
                message:
                    'Are you sure you want to delete all of the data in your test account (excluding settings)? This action is irreversible.',
                callback: function (result) {
                    if (result) {
                        $scope.clearingData = true;
                        $scope.error = null;

                        Company.clearData(
                            {
                                id: company.id,
                            },
                            {},
                            function () {
                                $scope.clearingData = false;
                                Core.flashMessage(
                                    'The test data in your account has been scheduled for clearing.',
                                    'success',
                                );
                            },
                            function () {
                                $scope.clearingData = false;
                                Core.showMessage('Could not delete your test data.', 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.webhookEventType = function (webhook) {
            return angular.equals(webhook.events, ['*']) ? 'all' : 'some';
        };

        loadApiKeys();
        loadWebhooks();
        loadOAuthApplications();
        Core.setTitle('Developers');

        function loadApiKeys() {
            if (!Feature.hasFeature('api')) {
                return;
            }

            $scope.loadingApiKeys = true;

            ApiKey.findAll(
                { paginate: 'none' },
                function (apiKeys) {
                    $scope.apiKeys = apiKeys;

                    $scope.loadingApiKeys = false;
                },
                function (result) {
                    $scope.loadingApiKeys = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadWebhooks() {
            if (!Feature.hasFeature('api')) {
                return;
            }

            $scope.loadingWebhooks = true;
            Webhook.findAll(
                {
                    'filter[protected]': 0,
                    include: 'secret',
                    paginate: 'none',
                },
                function (webhooks) {
                    $scope.webhooks = webhooks;
                    $scope.loadingWebhooks = false;
                },
                function (result) {
                    $scope.loadingWebhooks = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadOAuthApplications() {
            if (!Feature.hasFeature('api')) {
                return;
            }

            $scope.loadingOAuthApplications = true;
            OAuthApplication.findAll(
                {
                    include: 'secret',
                    paginate: 'none',
                },
                function (applications) {
                    $scope.oauthApplications = applications;
                    $scope.loadingOAuthApplications = false;
                },
                function (result) {
                    $scope.loadingOAuthApplications = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
