/* globals inflection */
(function () {
    'use strict';

    angular.module('app.components').directive('breadcrumbs', breadcrumbs);

    function breadcrumbs() {
        return {
            restrict: 'A',
            templateUrl: 'components/views/breadcrumbs.html',
            controller: [
                '$scope',
                '$rootScope',
                '$state',
                '$stateParams',
                function ($scope, $rootScope, $state, $stateParams) {
                    $scope.breadcrumbs = [];

                    $scope.go = $state.go.bind($state);

                    $rootScope.$on('$stateChangeSuccess', updateLocation);
                    $rootScope.$watch('modelTitle', updateLocation);
                    updateLocation();

                    function updateLocation() {
                        let state = $state.current.name.split('.');
                        let section = state[1];
                        let action = state[2];

                        let breadcrumbs = [];

                        switch (section) {
                            case 'customers':
                                breadcrumbs.push({
                                    state: 'manage.customers.browse',
                                    title: 'Customers',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                } else if (action === 'new') {
                                    breadcrumbs.push({
                                        title: 'New',
                                    });
                                } else if (action === 'import') {
                                    breadcrumbs.push({
                                        title: 'Import',
                                    });
                                } else if (action === 'export') {
                                    breadcrumbs.push({
                                        title: 'Export',
                                    });
                                }
                                break;
                            case 'customer':
                                breadcrumbs.push({
                                    state: 'manage.customers.browse',
                                    title: 'Customers',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.customer.view.summary',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                }
                                break;

                            case 'estimates':
                                breadcrumbs.push({
                                    state: 'manage.estimates.browse',
                                    title: 'Estimates',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                } else if (action === 'new' || action === 'newWithCustomer') {
                                    breadcrumbs.push({
                                        title: 'New',
                                    });
                                }
                                break;
                            case 'estimate':
                                breadcrumbs.push({
                                    state: 'manage.estimates.browse',
                                    title: 'Estimates',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.estimate.view.summary',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                } else if (action === 'edit') {
                                    breadcrumbs.push({
                                        title: 'Edit',
                                    });
                                } else if (action === 'duplicate') {
                                    breadcrumbs.push({
                                        title: 'Duplicate',
                                    });
                                }
                                break;

                            case 'invoices':
                                breadcrumbs.push({
                                    state: 'manage.invoices.browse',
                                    title: 'Invoices',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                } else if (action === 'new' || action === 'newWithCustomer') {
                                    breadcrumbs.push({
                                        title: 'New',
                                    });
                                } else if (action === 'export') {
                                    breadcrumbs.push({
                                        title: 'Export',
                                    });
                                }
                                break;
                            case 'invoice':
                                breadcrumbs.push({
                                    state: 'manage.invoices.browse',
                                    title: 'Invoices',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.invoice.view.summary',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                } else if (action === 'edit') {
                                    breadcrumbs.push({
                                        title: 'Edit',
                                    });
                                } else if (action === 'duplicate') {
                                    breadcrumbs.push({
                                        title: 'Duplicate',
                                    });
                                }
                                break;

                            case 'credit_notes':
                                breadcrumbs.push({
                                    state: 'manage.credit_notes.browse',
                                    title: 'Credit Notes',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                } else if (
                                    action === 'new' ||
                                    action === 'newWithInvoice' ||
                                    action === 'newWithCustomer'
                                ) {
                                    breadcrumbs.push({
                                        title: 'New',
                                    });
                                }
                                break;
                            case 'credit_note':
                                breadcrumbs.push({
                                    state: 'manage.credit_notes.browse',
                                    title: 'Credit Notes',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.credit_note.view.summary',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                } else if (action === 'edit') {
                                    breadcrumbs.push({
                                        title: 'Edit',
                                    });
                                } else if (action === 'duplicate') {
                                    breadcrumbs.push({
                                        title: 'Duplicate',
                                    });
                                }
                                break;

                            case 'payment_plans':
                                breadcrumbs.push({
                                    state: 'manage.payment_plans.browse',
                                    title: 'Payment Plans',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                }
                                break;

                            case 'subscriptions':
                                breadcrumbs.push({
                                    state: 'manage.subscriptions.browse',
                                    title: 'Subscriptions',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                }
                                break;
                            case 'subscription':
                                breadcrumbs.push({
                                    state: 'manage.subscriptions.browse',
                                    title: 'Subscriptions',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.subscription.view.summary',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                }
                                break;

                            case 'transactions':
                                breadcrumbs.push({
                                    state: 'manage.transactions.browse',
                                    title: 'Transactions',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                } else if (action === 'export') {
                                    breadcrumbs.push({
                                        title: 'Export',
                                    });
                                }
                                break;
                            case 'transaction':
                                breadcrumbs.push({
                                    state: 'manage.transactions.browse',
                                    title: 'Transactions',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.transaction.view.summary',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                }
                                break;

                            case 'payments':
                                breadcrumbs.push({
                                    state: 'manage.payments.browse',
                                    title: 'Payments',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                }

                                break;

                            case 'payment':
                                breadcrumbs.push({
                                    state: 'manage.payments.browse',
                                    title: 'Payments',
                                });

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                }
                                break;

                            case 'activities':
                                breadcrumbs.push({
                                    title: 'Activities',
                                });
                                break;

                            case 'events':
                                breadcrumbs.push({
                                    title: 'Activity Log',
                                });
                                break;

                            case 'imports':
                                breadcrumbs.push({
                                    state: 'manage.imports.browse',
                                    title: 'Imports',
                                });

                                if (action === 'browse') {
                                    breadcrumbs.push({
                                        title: 'Browse',
                                    });
                                } else if (action === 'start' || action === 'new') {
                                    breadcrumbs.push({
                                        title: 'New',
                                    });
                                }

                                if (state[3]) {
                                    breadcrumbs.push({
                                        title: inflection.titleize(state[3]),
                                    });
                                }
                                break;
                            case 'import':
                                breadcrumbs.push({
                                    state: 'manage.imports.browse',
                                    title: 'Imports',
                                });

                                if ($rootScope.modelTitle) {
                                    breadcrumbs.push({
                                        state: 'manage.import.view',
                                        stateParams: {
                                            id: $stateParams.id,
                                        },
                                        title: $rootScope.modelTitle,
                                    });
                                }

                                if (action === 'view') {
                                    breadcrumbs.push({
                                        title: 'Details',
                                    });
                                }
                                break;

                            case 'automations':
                                breadcrumbs.push({
                                    state: 'manage.settings.automation.list',
                                    title: 'Automations',
                                });

                                if (action === 'new_workflow') {
                                    breadcrumbs.push({
                                        title: 'New',
                                    });
                                } else if (action === 'edit_workflow' || action === 'builder') {
                                    breadcrumbs.push({
                                        title: 'Edit',
                                    });
                                } else if (action === 'duplicate_workflow') {
                                    breadcrumbs.push({
                                        title: 'Duplicate',
                                    });
                                }
                                break;

                            case 'settings':
                                if (action === 'default') {
                                    breadcrumbs.push({
                                        title: 'Settings',
                                    });
                                } else {
                                    breadcrumbs.push({
                                        state: 'manage.settings.default',
                                        title: 'Settings',
                                    });

                                    if (action === 'business') {
                                        breadcrumbs.push({
                                            title: 'Business Profile',
                                        });
                                    } else if (action === 'appearance') {
                                        breadcrumbs.push({
                                            title: 'Appearance',
                                        });
                                    } else if (action === 'items') {
                                        breadcrumbs.push({
                                            title: 'Items',
                                        });
                                    } else if (action === 'coupons') {
                                        breadcrumbs.push({
                                            title: 'Coupons',
                                        });
                                    } else if (action === 'taxes') {
                                        breadcrumbs.push({
                                            title: 'Sales Tax',
                                        });
                                    } else if (action === 'bundles') {
                                        breadcrumbs.push({
                                            title: 'Bundles',
                                        });
                                    } else if (action === 'gl_accounts') {
                                        breadcrumbs.push({
                                            title: 'G/L Accounts',
                                        });
                                    } else if (action === 'emails') {
                                        breadcrumbs.push({
                                            title: 'Emails',
                                        });
                                    } else if (action === 'chasing') {
                                        breadcrumbs.push({
                                            title: 'Chasing',
                                        });
                                    } else if (action === 'payments') {
                                        breadcrumbs.push({
                                            title: 'Payments',
                                        });
                                    } else if (action === 'team') {
                                        breadcrumbs.push({
                                            title: 'Team',
                                        });
                                    } else if (action === 'developers') {
                                        breadcrumbs.push({
                                            title: 'Developers',
                                        });
                                    } else if (action === 'accounts_receivable') {
                                        breadcrumbs.push({
                                            title: 'Accounts Receivable',
                                        });
                                    } else if (action === 'billing') {
                                        breadcrumbs.push({
                                            title: 'Billing',
                                        });
                                    } else if (action === 'app') {
                                        breadcrumbs.push({
                                            state: 'manage.settings.apps',
                                            title: 'Apps',
                                        });
                                        breadcrumbs.push({
                                            title: 'Details',
                                        });
                                    } else if (action === 'apps') {
                                        breadcrumbs.push({
                                            title: 'Apps',
                                        });
                                    } else if (action === 'cancel') {
                                        breadcrumbs.push({
                                            state: 'manage.settings.billing',
                                            title: 'Billing',
                                        });

                                        breadcrumbs.push({
                                            title: 'Cancel',
                                        });
                                    } else if (action === 'plans') {
                                        breadcrumbs.push({
                                            title: 'Plans',
                                        });
                                    } else if (action === 'custom_fields') {
                                        breadcrumbs.push({
                                            title: 'Custom Fields',
                                        });
                                    } else if (action === 'sign_up_pages') {
                                        breadcrumbs.push({
                                            title: 'Sign Up Pages',
                                        });
                                    } else if (action === 'customer_portal') {
                                        breadcrumbs.push({
                                            title: 'Customer Portal',
                                        });
                                    } else if (action === 'late_fees') {
                                        breadcrumbs.push({
                                            title: 'Late Fees',
                                        });
                                    } else if (action === 'payment_terms') {
                                        breadcrumbs.push({
                                            title: 'Payment Terms',
                                        });
                                    } else if (action === 'subscription_billing') {
                                        breadcrumbs.push({
                                            title: 'Subscription Billing',
                                        });
                                    } else if (action === 'cash_application') {
                                        breadcrumbs.push({
                                            title: 'Cash Application',
                                        });
                                    } else if (action === 'notifications') {
                                        breadcrumbs.push({
                                            title: 'Notifications',
                                        });
                                    } else if (action === 'automations') {
                                        breadcrumbs.push({
                                            title: 'Automations',
                                        });
                                    } else if (action === 'accounts_payable') {
                                        breadcrumbs.push({
                                            title: 'Accounts Payable',
                                        });
                                    } else if (action === 'approval_workflows') {
                                        breadcrumbs.push({
                                            title: 'Approval Workflows',
                                        });
                                    } else if (action === 'bank_accounts') {
                                        breadcrumbs.push({
                                            title: 'Bank Accounts',
                                        });
                                    } else if (action === 'credit_cards') {
                                        breadcrumbs.push({
                                            title: 'Cards',
                                        });
                                    }
                                }
                                break;
                        }

                        $scope.breadcrumbs = breadcrumbs;
                    }
                },
            ],
        };
    }
})();
