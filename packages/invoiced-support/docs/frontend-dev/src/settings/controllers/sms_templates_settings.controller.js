/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('SmsTemplatesSettingsController', SmsTemplatesSettingsController);

    SmsTemplatesSettingsController.$inject = [
        '$scope',
        '$modal',
        'LeavePageWarning',
        'Company',
        'selectedCompany',
        'SmsTemplate',
        'Core',
    ];

    function SmsTemplatesSettingsController(
        $scope,
        $modal,
        LeavePageWarning,
        Company,
        selectedCompany,
        SmsTemplate,
        Core,
    ) {
        $scope.smsTemplates = [];

        $scope.newTemplate = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-sms-template.html',
                controller: 'EditSmsTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return {
                            name: '',
                            template_engine: 'twig',
                            message: '{{contact_name}}, your account balance of {{account_balance}} is now due {{url}}',
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (smsTemplate) {
                    $scope.smsTemplates.push(smsTemplate);
                    sortTemplates();
                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.clone = function (smsTemplate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-sms-template.html',
                controller: 'EditSmsTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return {
                            name: smsTemplate.name,
                            message: smsTemplate.message,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (newTemplate) {
                    $scope.smsTemplates.push(newTemplate);
                    sortTemplates();

                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.edit = function (smsTemplate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-sms-template.html',
                controller: 'EditSmsTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return smsTemplate;
                    },
                },
            });

            modalInstance.result.then(
                function (updatedTemplate) {
                    angular.extend(smsTemplate, updatedTemplate);

                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (smsTemplate) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this SMS template?',
                callback: function (result) {
                    if (result) {
                        SmsTemplate.delete(
                            {
                                id: smsTemplate.id,
                            },
                            function () {
                                $scope.error = null;

                                Core.flashMessage('Your SMS template has been deleted.', 'success');

                                loadTemplates();
                            },
                            function (result) {
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('SMS Templates');

        loadTemplates();

        function loadTemplates() {
            $scope.loadingTemplates = true;

            // load all SMS templates
            SmsTemplate.findAll(
                { paginate: 'none' },
                function (smsTemplates) {
                    $scope.smsTemplates = smsTemplates;
                    sortTemplates();
                    $scope.loadingTemplates = false;
                },
                function (result) {
                    $scope.loadingTemplates = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function sortTemplates() {
            // sorts templates by name
            $scope.smsTemplates.sort(function (a, b) {
                if (a.name !== b.name) {
                    return a.name < b.name ? -1 : 1;
                }

                return 0;
            });
        }
    }
})();
