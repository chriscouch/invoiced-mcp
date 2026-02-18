(function () {
    'use strict';

    angular.module('app.dashboard').factory('PresetDashboards', PresetDashboards);

    PresetDashboards.$inject = ['Feature', 'selectedCompany', 'localStorageService'];

    function PresetDashboards(Feature, selectedCompany, localStorageService) {
        return {
            all: getAllDashboards,
        };

        function getAllDashboards() {
            if (typeof selectedCompany.dashboards !== 'undefined') {
                return selectedCompany.dashboards;
            }

            let dashboards = [];

            if (Feature.hasFeature('accounts_receivable')) {
                let arDashboard = {
                    name: 'Accounts Receivable',
                    rows: [
                        {
                            columns: [
                                {
                                    size: 5,
                                    component: 'action-items',
                                },
                                {
                                    size: 7,
                                    component: 'grid',
                                    grid: {
                                        rows: [
                                            {
                                                columns: [
                                                    {
                                                        size: 4,
                                                        component: 'time-to-pay',
                                                        options: {
                                                            gauge: true,
                                                            min: 0,
                                                            max: 45,
                                                        },
                                                    },
                                                    {
                                                        size: 4,
                                                        component: 'collections-efficiency',
                                                        options: {
                                                            gauge: true,
                                                            min: 0,
                                                            max: 100,
                                                        },
                                                    },
                                                    {
                                                        size: 4,
                                                        component: 'days-sales-outstanding',
                                                        options: {
                                                            gauge: true,
                                                            min: 0,
                                                            max: 45,
                                                        },
                                                    },
                                                ],
                                            },
                                            {
                                                columns: [
                                                    {
                                                        size: 4,
                                                        component: 'ar-balance',
                                                    },
                                                    {
                                                        size: 4,
                                                        component: 'total-open-items',
                                                    },
                                                    {
                                                        size: 4,
                                                        component: 'expected-payments',
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                },
                            ],
                        },
                        {
                            columns: [
                                {
                                    size: 12,
                                    component: 'grid',
                                    grid: {
                                        rows: [
                                            {
                                                columns: [
                                                    {
                                                        size: 12,
                                                        component: 'ar-aging-bar',
                                                    },
                                                ],
                                            },
                                            {
                                                columns: [
                                                    {
                                                        size: 5,
                                                        component: 'ar-aging',
                                                    },
                                                    {
                                                        size: 7,
                                                        component: 'top-debtors',
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                },
                            ],
                        },
                        {
                            columns: [
                                {
                                    size: 7,
                                    component: 'activity-chart',
                                },
                            ],
                        },
                    ],
                };

                // Add recent activity if not under a customer restriction
                if (!hasCustomerRestriction()) {
                    arDashboard.rows[2].columns.push({
                        size: 5,
                        component: 'recent-activity',
                    });
                }

                // Add setup checklist
                if (shouldShowSetupChecklist()) {
                    arDashboard.rows.splice(0, 0, {
                        columns: [
                            {
                                size: 12,
                                component: 'setup-checklist',
                            },
                        ],
                    });
                }

                // Add activation banner
                if (shouldShowActivationBanner()) {
                    arDashboard.rows.splice(0, 0, {
                        columns: [
                            {
                                class: 'no-box',
                                size: 12,
                                component: 'activation-banner',
                            },
                        ],
                    });
                }

                dashboards.push(arDashboard);
            }

            if (Feature.hasFeature('accounts_payable')) {
                let apDashboard = {
                    name: 'Accounts Payable',
                    rows: [
                        {
                            columns: [
                                {
                                    size: 6,
                                    component: 'action-items',
                                },
                                {
                                    size: 6,
                                    component: 'grid',
                                    grid: {
                                        rows: [
                                            {
                                                columns: [
                                                    {
                                                        size: 6,
                                                        component: 'ap-balance',
                                                    },
                                                    {
                                                        size: 6,
                                                        component: 'days-payable-outstanding',
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                },
                            ],
                        },
                        {
                            columns: [
                                {
                                    size: 12,
                                    component: 'grid',
                                    grid: {
                                        rows: [
                                            {
                                                columns: [
                                                    {
                                                        size: 12,
                                                        component: 'ap-aging-bar',
                                                    },
                                                ],
                                            },
                                            {
                                                columns: [
                                                    {
                                                        size: 6,
                                                        component: 'ap-aging',
                                                    },
                                                    {
                                                        size: 6,
                                                        component: 'bills-by-status',
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                },
                            ],
                        },
                        {
                            columns: [
                                {
                                    size: 6,
                                    component: 'top-vendors',
                                },
                                {
                                    size: 6,
                                    component: 'recent-activity',
                                },
                            ],
                        },
                    ],
                };

                // Add setup checklist
                if (shouldShowSetupChecklist()) {
                    apDashboard.rows.splice(0, 0, {
                        columns: [
                            {
                                size: 12,
                                component: 'setup-checklist',
                            },
                        ],
                    });
                }

                // Add activation banner
                if (shouldShowActivationBanner()) {
                    apDashboard.rows.splice(0, 0, {
                        columns: [
                            {
                                class: 'no-box',
                                size: 12,
                                component: 'activation-banner',
                            },
                        ],
                    });
                }

                dashboards.push(apDashboard);
            }
            selectedCompany.dashboards = dashboards;

            return dashboards;
        }

        function hasCustomerRestriction() {
            return selectedCompany.restriction_mode === 'owner' || selectedCompany.restriction_mode === 'custom_field';
        }

        function shouldShowSetupChecklist() {
            if (Feature.hasFeature('not_activated')) {
                return false;
            }

            // check if user has already marked all complete
            if (localStorageService.get('completedSetup.' + selectedCompany.id)) {
                return false;
            }

            // Currently the setup checklist is disabled and not shown to users
            return false;
        }

        function shouldShowActivationBanner() {
            return Feature.hasFeature('not_activated');
        }
    }
})();
