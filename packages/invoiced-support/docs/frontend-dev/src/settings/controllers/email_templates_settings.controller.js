/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('EmailTemplatesSettingsController', EmailTemplatesSettingsController);

    EmailTemplatesSettingsController.$inject = [
        '$scope',
        '$modal',
        'LeavePageWarning',
        'Company',
        'selectedCompany',
        'EmailTemplate',
        'InvoicedConfig',
        'Core',
        'Feature',
    ];

    function EmailTemplatesSettingsController(
        $scope,
        $modal,
        LeavePageWarning,
        Company,
        selectedCompany,
        EmailTemplate,
        InvoicedConfig,
        Core,
        Feature,
    ) {
        $scope.emailTemplates = [];

        $scope.selectedType = '*';
        $scope.types = ['chasing', 'invoice', 'statement', 'credit_note', 'payment_plan', 'transaction'];

        let defaultTemplates = [
            'new_invoice_email',
            'unpaid_invoice_email',
            'late_payment_reminder_email',
            'paid_invoice_email',
            'payment_plan_onboard_email',
            'credit_note_email',
            'payment_receipt_email',
            'refund_email',
            'statement_email',
        ];

        if (Feature.hasFeature('autopay')) {
            defaultTemplates.push('auto_payment_failed_email');
        }

        if (Feature.hasFeature('estimates')) {
            $scope.types.push('estimate');

            defaultTemplates.push('estimate_email');
        }

        if (Feature.hasFeature('subscriptions')) {
            $scope.types.push('subscription');

            defaultTemplates.push('subscription_confirmation_email');
            defaultTemplates.push('subscription_renews_soon_email');
            defaultTemplates.push('subscription_canceled_email');
        }

        $scope.selectType = function (type) {
            $scope.selectedType = type;
        };

        $scope.filterToType = function (template) {
            if ($scope.selectedType === '*') {
                return true;
            }

            return template.type === $scope.selectedType;
        };

        $scope.newTemplate = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-email-template.html',
                controller: 'EditEmailTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return {
                            options: {},
                        };
                    },
                    options: function () {
                        return {
                            canEditName: true,
                            canEditType: true,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (emailTemplate) {
                    $scope.emailTemplates.push(emailTemplate);
                    sortTemplates();
                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.clone = function (emailTemplate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-email-template.html',
                controller: 'EditEmailTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return {
                            name: emailTemplate.name,
                            type: emailTemplate.type,
                            subject: emailTemplate.subject,
                            body: emailTemplate.body,
                            options: emailTemplate.options,
                        };
                    },
                    options: function () {
                        return {
                            canEditName: true,
                            canEditType: false,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (newTemplate) {
                    $scope.emailTemplates.push(newTemplate);
                    sortTemplates();

                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.edit = function (emailTemplate) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-email-template.html',
                controller: 'EditEmailTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return emailTemplate;
                    },
                    options: function () {
                        return {
                            canEditName: true,
                            canEditType: false,
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (updatedTemplate) {
                    angular.extend(emailTemplate, updatedTemplate);

                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (emailTemplate) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this email template?',
                callback: function (result) {
                    if (result) {
                        EmailTemplate.delete(
                            {
                                id: emailTemplate.id,
                            },
                            function () {
                                $scope.error = null;

                                Core.flashMessage('Your email template has been deleted.', 'success');

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

        Core.setTitle('Email Templates');

        loadTemplates();

        function loadTemplates() {
            $scope.loadingTemplates = true;

            // load all email templates
            EmailTemplate.findAll(
                { paginate: 'none' },
                function (emailTemplates) {
                    // add in the default templates
                    // that have not been customized
                    let ids = {};
                    angular.forEach(emailTemplates, function (emailTemplate) {
                        ids[emailTemplate.id] = true;
                    });

                    angular.forEach(defaultTemplates, function (templateId) {
                        if (!ids[templateId]) {
                            emailTemplates.push(InvoicedConfig.templates.email[templateId]);
                        }
                    });

                    $scope.emailTemplates = emailTemplates;
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
            // sorts templates by type and then name
            $scope.emailTemplates.sort(function (a, b) {
                if (a.type != b.type) {
                    return a.type < b.type ? -1 : 1;
                }

                if (a.name != b.name) {
                    return a.name < b.name ? -1 : 1;
                }

                return 0;
            });

            let lastType = false;
            angular.forEach($scope.emailTemplates, function (emailTemplate) {
                if (defaultTemplates.indexOf(emailTemplate.id) !== -1) {
                    emailTemplate.isStandard = true;
                }

                if (!lastType || emailTemplate.type != lastType) {
                    lastType = emailTemplate.type;
                    emailTemplate.isFirst = true;
                } else {
                    emailTemplate.isFirst = false;
                }
            });
        }
    }
})();
