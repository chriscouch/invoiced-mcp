/* globals moment */
(function () {
    'use strict';

    angular.module('app.settings').controller('EmailTriggersSettingsController', EmailTriggersSettingsController);

    EmailTriggersSettingsController.$inject = [
        '$scope',
        '$modal',
        '$timeout',
        'LeavePageWarning',
        'Settings',
        'SmtpAccount',
        'EmailTemplate',
        'InvoicedConfig',
        'Core',
        'Feature',
        'DatePickerService',
    ];

    function EmailTriggersSettingsController(
        $scope,
        $modal,
        $timeout,
        LeavePageWarning,
        Settings,
        SmtpAccount,
        EmailTemplate,
        InvoicedConfig,
        Core,
        Feature,
        DatePickerService,
    ) {
        $scope.hasPaymentPlans = Feature.hasFeature('payment_plans');
        $scope.hasSubscriptions = Feature.hasFeature('subscriptions');
        $scope.hasAutoPay = Feature.hasFeature('autopay');
        $scope.hasEstimates = Feature.hasFeature('estimates');

        let triggerTemplates = [
            'new_invoice_email',
            'paid_invoice_email',
            'payment_plan_onboard_email',
            'credit_note_email',
            'payment_receipt_email',
            'refund_email',
            'auto_payment_failed_email',
            'subscription_confirmation_email',
            'subscription_renews_soon_email',
            'subscription_canceled_email',
            'estimate_email',
        ];

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.customize = function (template) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-email-template.html',
                controller: 'EditEmailTemplateController',
                size: 'lg',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return template;
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
                    if (updatedTemplate) {
                        angular.extend(template, updatedTemplate);
                    }

                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.save = saveTriggers;

        Core.setTitle('Email Triggers');

        loadTemplates();

        function loadTemplates() {
            $scope.loadingTemplates = true;

            // load all email templates
            EmailTemplate.findAll(
                {
                    sort: 'name ASC',
                    paginate: 'none',
                },
                function (emailTemplates) {
                    $scope.options = {};
                    $scope.emailTemplates = {};

                    // start with default options
                    angular.forEach(triggerTemplates, function (template) {
                        let options = {};
                        angular.forEach(InvoicedConfig.emailTemplateOptionsByTemplate[template], function (option) {
                            options[option.id] = option.default;
                        });
                        $scope.options[template] = options;
                        $scope.emailTemplates[template] = InvoicedConfig.templates.email[template];
                    });

                    // fill in any customizations
                    angular.forEach(emailTemplates, function (template) {
                        if (typeof $scope.options[template.id] !== 'undefined') {
                            angular.extend($scope.options[template.id], template.options);
                            angular.extend($scope.emailTemplates[template.id], template);
                        }
                    });

                    $scope.triggers = buildTriggerMap($scope.options);

                    $scope.loadingTemplates = false;
                },
                function (result) {
                    $scope.loadingTemplates = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function buildTriggerMap(options) {
            let triggers = {};

            // Invoice triggers
            triggers.new_invoice = !!options.new_invoice_email.send_on_issue;
            triggers.new_autopay_invoice = !!options.new_invoice_email.send_on_autopay_invoice;
            triggers.has_invoice_start_date = !!options.new_invoice_email.invoice_start_date;
            triggers.invoice_start_date = triggers.has_invoice_start_date
                ? moment.unix(options.new_invoice_email.invoice_start_date).toDate()
                : new Date();
            triggers.new_subscription_invoice = !!options.new_invoice_email.send_on_subscription_invoice;
            triggers.invoice_reminder_days = parseInt(options.new_invoice_email.send_reminder_every_days);
            triggers.invoice_reminder = triggers.invoice_reminder_days > 0;
            triggers.paid_invoice = !!options.paid_invoice_email.send_on_paid;

            // Payment Plan triggers
            triggers.new_payment_plan = !!options.payment_plan_onboard_email.send_on_issue;
            triggers.payment_plan_reminder_days = parseInt(options.payment_plan_onboard_email.send_reminder_every_days);
            triggers.payment_plan_reminder = triggers.payment_plan_reminder_days > 0;

            // Payment triggers
            triggers.send_receipt = !!options.payment_receipt_email.send_on_charge;
            triggers.send_refund = !!options.refund_email.send_on_charge;
            triggers.send_auto_payment_failed = !!options.auto_payment_failed_email.send_on_charge;

            // Credit note triggers
            triggers.new_credit_note = !!options.credit_note_email.send_on_issue;

            // Subscription triggers
            triggers.subscription_confirmation = !!options.subscription_confirmation_email.send_on_subscribe;
            triggers.subscription_renews_soon_days = parseInt(
                options.subscription_renews_soon_email.days_before_renewal,
            );
            triggers.subscription_renews_soon = triggers.subscription_renews_soon_days > 0;
            triggers.subscription_canceled = !!options.subscription_canceled_email.send_on_cancellation;

            // Estimate triggers
            triggers.new_estimate = !!options.estimate_email.send_on_issue;
            triggers.estimate_reminder_days = parseInt(options.estimate_email.send_reminder_every_days);
            triggers.estimate_reminder = triggers.estimate_reminder_days > 0;

            return triggers;
        }

        function saveTriggers(triggers) {
            $scope.saving = 0;

            // Invoice triggers
            saveOptions('new_invoice_email', {
                send_on_issue: triggers.new_invoice,
                send_on_autopay_invoice: triggers.new_autopay_invoice && triggers.new_invoice,
                send_on_subscription_invoice: triggers.new_subscription_invoice,
                send_reminder_every_days: triggers.invoice_reminder ? triggers.invoice_reminder_days : 0,
                invoice_start_date:
                    triggers.has_invoice_start_date && triggers.new_invoice
                        ? moment(triggers.invoice_start_date).endOf('day').unix()
                        : '',
            });

            saveOptions('paid_invoice_email', {
                send_on_paid: triggers.paid_invoice,
            });

            // Payment Plan triggers
            saveOptions('payment_plan_onboard_email', {
                send_on_issue: triggers.new_payment_plan,
                send_reminder_every_days: triggers.payment_plan_reminder ? triggers.payment_plan_reminder_days : 0,
            });

            // Payment triggers
            saveOptions('payment_receipt_email', {
                send_on_charge: triggers.send_receipt,
            });
            saveOptions('refund_email', {
                send_on_charge: triggers.send_refund,
            });
            saveOptions('auto_payment_failed_email', {
                send_on_charge: triggers.send_auto_payment_failed,
            });

            // Credit note triggers
            saveOptions('credit_note_email', {
                send_on_issue: triggers.new_credit_note,
            });

            // Subscription triggers
            saveOptions('subscription_confirmation_email', {
                send_on_subscribe: triggers.subscription_confirmation,
            });

            saveOptions('subscription_renews_soon_email', {
                days_before_renewal: triggers.subscription_renews_soon ? triggers.subscription_renews_soon_days : 0,
            });

            saveOptions('subscription_canceled_email', {
                send_on_cancellation: triggers.subscription_canceled,
            });

            // Estimate triggers
            saveOptions('estimate_email', {
                send_on_issue: triggers.new_estimate,
                send_reminder_every_days: triggers.estimate_reminder ? triggers.estimate_reminder_days : 0,
            });
        }

        function saveOptions(templateId, options) {
            $scope.saving++;

            let template = $scope.emailTemplates[templateId];
            angular.extend(template.options, options);

            // If the email template does not exist
            // then it needs to be created. This is
            // why we are passing in all of the template
            // parameters.
            EmailTemplate.edit(
                {
                    id: templateId,
                },
                {
                    name: template.name,
                    type: template.type,
                    subject: template.subject,
                    body: template.body,
                    options: template.options,
                },
                function () {
                    $scope.saving--;

                    if ($scope.saving === 0) {
                        Core.flashMessage('Your email triggers have been updated.', 'success');
                    }
                },
                function (result) {
                    $scope.saving--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
