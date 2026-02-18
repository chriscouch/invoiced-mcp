(function () {
    'use strict';

    angular.module('app.settings').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.settings', {
                url: '/settings',
                abstract: true,
                template: '<ui-view/>',
            })
            .state('manage.settings.default', {
                url: '',
                templateUrl: 'settings/views/index.html',
                controller: [
                    'Core',
                    function (Core) {
                        Core.setTitle('Settings');
                    },
                ],
                resolve: {
                    allowed: [
                        'userBootstrap',
                        '$q',
                        'Permission',
                        function (userBootstrap, $q, Permission) {
                            if (
                                Permission.hasSomePermissions([
                                    'catalog.edit',
                                    'settings.edit',
                                    'business.admin',
                                    'business.billing',
                                ])
                            ) {
                                return true;
                            }

                            return $q.reject('Not Authorized');
                        },
                    ],
                },
            })

            /* Business Admin */

            .state('manage.settings.team', {
                url: '/members',
                templateUrl: 'settings/views/team.html',
                controller: 'TeamSettingsController',
                resolve: {
                    allowed: allowed('business.admin'),
                },
                abstract: true,
            })
            .state('manage.settings.team.users', {
                url: '/',
                templateUrl: 'settings/views/users.html',
                controller: 'UsersSettingsController',
            })
            .state('manage.settings.team.roles', {
                url: '/roles',
                templateUrl: 'settings/views/roles.html',
                controller: 'RolesSettingsController',
                resolve: {
                    allowed: hasFeature('roles'),
                },
            })
            .state('manage.settings.team.saml', {
                url: '/saml',
                templateUrl: 'settings/views/saml.html',
                controller: 'SamlSettingsController',
                resolve: {
                    allowed: hasFeature('saml'),
                },
            })
            .state('manage.settings.automation', {
                url: '/automations',
                templateUrl: 'settings/views/automation.html',
                controller: () => {},
                resolve: {
                    allowed: allowed('settings.edit'),
                },
                abstract: true,
            })
            .state('manage.settings.automation.list', {
                url: '/',
                templateUrl: 'settings/views/automation-automations.html',
                controller: 'AutomationSettingsController',
            })
            .state('manage.settings.automation.runs', {
                url: '/runs',
                templateUrl: 'settings/views/automation-runs.html',
                controller: 'AutomationRunsController',
            })
            .state('manage.settings.developers', {
                url: '/developers',
                templateUrl: 'settings/views/developers.html',
                controller: 'DeveloperSettingsController',
                resolve: {
                    allowed: allowed('business.admin'),
                },
            })
            .state('manage.settings.developers.index', {
                url: '',
                controller: [
                    '$state',
                    function ($state) {
                        $state.go('^.api_keys');
                    },
                ],
            })
            .state('manage.settings.developers_api_keys', {
                url: '/developers/api_keys',
                controller: [
                    '$state',
                    function ($state) {
                        $state.go('^.developers');
                    },
                ],
            })
            .state('manage.settings.developers_webhooks', {
                url: '/developers/webhooks',
                controller: [
                    '$state',
                    function ($state) {
                        $state.go('^.developers');
                    },
                ],
            })
            .state('manage.settings.developers_single_sign_on', {
                url: '/developers/single_sign_on',
                controller: [
                    '$state',
                    function ($state) {
                        $state.go('^.developers');
                    },
                ],
            })

            /* Billing */

            .state('manage.settings.billing', {
                url: '/billing',
                templateUrl: 'settings/views/billing.html',
                controller: 'BillingSettingsController',
                resolve: {
                    allowed: allowed('business.billing'),
                },
            })
            .state('manage.settings.cancel', {
                url: '/cancel',
                templateUrl: 'settings/views/cancel.html',
                controller: 'CancelAccountController',
                resolve: {
                    allowed: allowed('business.billing'),
                },
            })

            /* Business Settings */

            .state('manage.settings.business', {
                url: '/business',
                templateUrl: 'settings/views/business-profile.html',
                controller: 'BusinessProfileSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })

            /* A/P Settings */

            .state('manage.settings.accounts_payable', {
                url: '/accounts-payable',
                templateUrl: 'settings/views/accounts-payable.html',
                controller: 'AccountsPayableSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.approval_workflows', {
                url: '/approval-workflows',
                templateUrl: 'settings/views/approval-workflows.html',
                controller: 'ApprovalWorkflowSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.bank_accounts', {
                url: '/bank-accounts',
                templateUrl: 'settings/views/bank-accounts.html',
                controller: 'BankAccountSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.credit_cards', {
                url: '/cards',
                templateUrl: 'settings/views/credit-cards.html',
                controller: 'CreditCardsSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })

            /* A/R Settings */

            .state('manage.settings.accounts_receivable', {
                url: '/accounts-receivable',
                templateUrl: 'settings/views/accounts-receivable.html',
                controller: 'AccountsReceivableSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.appearance', {
                url: '/appearance',
                templateUrl: 'settings/views/appearance.html',
                controller: 'AppearanceSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.custom_fields', {
                url: '/custom_fields',
                templateUrl: 'settings/views/custom-fields.html',
                controller: 'CustomFieldsSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.sign_up_pages', {
                url: '/sign_up_pages',
                templateUrl: 'settings/views/sign-up-pages.html',
                controller: 'SignUpPagesSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.cash_application', {
                url: '/cash_application',
                templateUrl: 'settings/views/cash-application.html',
                resolve: {
                    allowed: hasFeature('cash_application'),
                },
                abstract: true,
            })
            .state('manage.settings.cash_application.bank_accounts', {
                url: '/bank_accounts',
                templateUrl: 'settings/views/cash-application-bank-accounts.html',
                controller: 'CashApplicationBankAccountsController',
            })
            .state('manage.settings.cash_application.settings', {
                url: '/',
                templateUrl: 'settings/views/cash-application-settings.html',
                controller: 'CashApplicationSettingsController',
            })
            .state('manage.settings.cash_application.rules', {
                url: '/rules',
                templateUrl: 'settings/views/cash-application-rules.html',
                controller: 'CashApplicationRulesController',
            })

            /* Emails */

            .state('manage.settings.emails', {
                url: '/emails',
                templateUrl: 'settings/views/emails.html',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
                abstract: true,
            })
            .state('manage.settings.emails.templates', {
                url: '/',
                templateUrl: 'settings/views/email-templates.html',
                controller: 'EmailTemplatesSettingsController',
                resolve: {
                    allowed: hasFeature('email_sending'),
                },
            })
            .state('manage.settings.emails.sms_templates', {
                url: '/sms_templates',
                templateUrl: 'settings/views/sms-templates.html',
                controller: 'SmsTemplatesSettingsController',
                resolve: {
                    allowed: hasFeature('sms'),
                },
            })
            .state('manage.settings.emails.triggers', {
                url: '/triggers',
                templateUrl: 'settings/views/email-triggers.html',
                controller: 'EmailTriggersSettingsController',
                resolve: {
                    allowed: hasFeature('email_sending'),
                },
            })
            .state('manage.settings.emails.delivery', {
                url: '/delivery',
                templateUrl: 'settings/views/email-delivery-settings.html',
                controller: 'EmailDeliverySettingsController',
                resolve: {
                    allowed: hasFeature('email_sending'),
                },
            })
            .state('manage.settings.chasing_legacy', {
                url: '/chasing-legacy',
                templateUrl: 'settings/views/chasing-legacy.html',
                controller: 'ChasingLegacySettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.chasing', {
                url: '/chasing',
                templateUrl: 'settings/views/chasing.html',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
                abstract: true,
            })
            .state('manage.settings.chasing.customers', {
                url: '/',
                templateUrl: 'settings/views/chasing-customer-settings.html',
                controller: 'ChasingCustomerSettingsController',
            })
            .state('manage.settings.chasing.invoices', {
                url: '/invoices',
                templateUrl: 'settings/views/chasing-invoice-settings.html',
                controller: 'ChasingInvoiceSettingsController',
            })
            .state('manage.settings.chasing.roles', {
                url: '/roles',
                templateUrl: 'settings/views/contact-role-settings.html',
                controller: 'ContactRoleSettingsController',
            })
            .state('manage.settings.payments', {
                url: '/payments',
                templateUrl: 'settings/views/payments.html',
                controller: 'PaymentsSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
                abstract: true,
            })
            .state('manage.settings.payments.methods', {
                url: '',
                templateUrl: 'settings/views/payment-methods.html',
                controller: 'PaymentMethodsSettingsController',
            })
            .state('manage.settings.payments.gateways', {
                url: '/gateways',
                templateUrl: 'settings/views/payment-gateways.html',
                controller: 'PaymentGatewaysSettingsController',
            })
            .state('manage.settings.payments.account', {
                url: '/account',
                templateUrl: 'settings/views/flywire-payments.html',
                controller: 'FlywirePaymentsSettingsController',
            })
            .state('manage.settings.customer_portal', {
                url: '/customer_portal',
                templateUrl: 'settings/views/customer-portal.html',
                controller: 'CustomerPortalSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.subscription_billing', {
                url: '/subscription-billing',
                templateUrl: 'settings/views/subscription-billing.html',
                controller: 'SubscriptionBillingSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.late_fees', {
                url: '/late_fees',
                templateUrl: 'settings/views/late-fee-schedules.html',
                controller: 'LateFeeSettingsController',
            })
            .state('manage.settings.payment_terms', {
                url: '/payment_terms',
                templateUrl: 'settings/views/payment-terms.html',
                controller: 'PaymentTermsSettingsController',
            })

            /* Apps */

            .state('manage.settings.apps', {
                url: '/apps',
                templateUrl: 'integrations/views/apps.html',
                controller: 'AppsSettingsController',
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.app', {
                url: '/apps/:id',
                templateUrl: 'integrations/views/app-details.html',
                controller: 'AppDetailsController',
                abstract: true,
                resolve: {
                    allowed: allowed('settings.edit'),
                },
            })
            .state('manage.settings.app.overview', {
                url: '',
                templateUrl: 'integrations/views/app-overview.html',
            })
            .state('manage.settings.app.configuration', {
                url: '/configuration',
                templateUrl: function ($stateParams) {
                    return 'integrations/views/configuration/' + $stateParams.id + '.html';
                },
                controllerProvider: function ($stateParams) {
                    let id = $stateParams.id;
                    if (id === 'avalara') {
                        return 'AvalaraSettingsController';
                    } else if (id === 'business_central') {
                        return 'BusinessCentralSettingsController';
                    } else if (id === 'chartmogul') {
                        return 'ChartMogulSettingsController';
                    } else if (id === 'earth_class_mail') {
                        return 'EarthClassMailSettingsController';
                    } else if (id === 'intacct') {
                        return 'IntacctSettingsController';
                    } else if (id === 'lob') {
                        return 'LobSettingsController';
                    } else if (id === 'netsuite') {
                        return 'NetSuiteSettingsController';
                    } else if (id === 'quickbooks_desktop') {
                        return 'QuickBooksDesktopSettingsController';
                    } else if (id === 'quickbooks_online') {
                        return 'QuickBooksOnlineSettingsController';
                    } else if (id === 'sage_accounting') {
                        return 'SageAccountingSettingsController';
                    } else if (id === 'twilio') {
                        return 'TwilioSettingsController';
                    } else if (id === 'xero') {
                        return 'XeroSettingsController';
                    }

                    return '';
                },
            })
            .state('manage.settings.app.accounting_sync', {
                url: '/accounting_sync',
                templateUrl: 'integrations/views/accounting-sync.html',
                controller: 'AccountingSyncController',
            })
            .state('manage.settings.app.initial_data_sync', {
                url: '/initial_data_sync',
                templateUrl: 'integrations/views/initial-data-sync.html',
                controller: 'InitialDataSyncController',
            })

            /* Catalog Management */

            .state('manage.settings.items', {
                url: '/items',
                templateUrl: 'settings/views/items.html',
                controller: 'ItemsSettingsController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.settings.plans', {
                url: '/plans',
                templateUrl: 'settings/views/plans.html',
                controller: 'PlansSettingsController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.settings.coupons', {
                url: '/coupons',
                templateUrl: 'settings/views/coupons.html',
                controller: 'CouponsSettingsController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.settings.taxes', {
                url: '/taxes',
                templateUrl: 'settings/views/taxes.html',
                controller: 'TaxSettingsController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
                abstract: true,
            })
            .state('manage.settings.taxes.rates', {
                url: '/',
                templateUrl: 'settings/views/tax-rates.html',
                controller: 'TaxRatesSettingsController',
            })
            .state('manage.settings.taxes.rules', {
                url: '/rules',
                templateUrl: 'settings/views/tax-rules.html',
                controller: 'TaxRulesSettingsController',
            })
            .state('manage.settings.bundles', {
                url: '/bundles',
                templateUrl: 'settings/views/bundles.html',
                controller: 'BundlesSettingsController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })
            .state('manage.settings.gl_accounts', {
                url: '/gl_accounts',
                templateUrl: 'settings/views/gl-accounts.html',
                controller: 'GlAccountsSettingsController',
                resolve: {
                    allowed: allowed('catalog.edit'),
                },
            })

            /* User Notifications */

            .state('manage.settings.notifications', {
                url: '/notifications?tab',
                templateUrl: 'settings/views/notifications.html',
                controller: 'NotificationSettingsController',
            });
    }

    function allowed(permission) {
        return [
            'userBootstrap',
            '$q',
            'Permission',
            function (userBootstrap, $q, Permission) {
                if (Permission.hasPermission(permission)) {
                    return true;
                }

                return $q.reject('Not Authorized');
            },
        ];
    }

    function hasFeature(feature) {
        return [
            'userBootstrap',
            '$q',
            'Feature',
            function (userBootstrap, $q, Feature) {
                if (Feature.hasFeature(feature)) {
                    return true;
                }

                return $q.reject('Not Authorized');
            },
        ];
    }
})();
