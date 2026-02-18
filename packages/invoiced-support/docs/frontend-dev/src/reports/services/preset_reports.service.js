(function () {
    'use strict';

    angular.module('app.reports').factory('PresetReports', PresetReports);

    PresetReports.$inject = ['Feature', 'selectedCompany'];

    function PresetReports(Feature, selectedCompany) {
        return {
            all: getAllReports,
            get: getReport,
        };

        function getAllReports() {
            if (typeof selectedCompany.reports !== 'undefined') {
                return selectedCompany.reports;
            }

            let hasCurrency = Feature.hasFeature('multi_currency') && selectedCompany.currencies.length > 1;

            let reports = [
                {
                    title: 'General',
                    reports: [
                        {
                            id: 'a_r_overview',
                            name: 'A/R Overview',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'aging_detail',
                            name: 'A/R Aging Detail',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                currency: hasCurrency,
                                customerCustomFields: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'aging_summary',
                            name: 'A/R Aging Summary',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                currency: hasCurrency,
                                customerCustomFields: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'invoiced_active_users',
                            name: 'Invoiced Active Users',
                        },
                        {
                            id: 'installment_aging_detail',
                            name: 'Installment Aging Detail',
                            features: ['accounts_receivable', 'payment_plans'],
                            availableParameters: {
                                currency: hasCurrency,
                                customerCustomFields: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'installment_aging_summary',
                            name: 'Installment Aging Summary',
                            features: ['accounts_receivable', 'payment_plans'],
                            availableParameters: {
                                currency: hasCurrency,
                                customerCustomFields: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'reconciliation',
                            name: 'Reconciliation',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'tax_summary',
                            name: 'Sales Tax Summary',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                                taxRecognitionDate: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'customer_portal_adoption',
                            name: 'Customer Portal Adoption',
                            features: ['accounts_receivable', 'billing_portal'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                    ],
                },
                {
                    title: 'Collections',
                    reports: [
                        {
                            id: 'collection_notes',
                            name: 'Collection Notes',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'customer_time_to_pay',
                            name: 'Customer Time to Pay',
                            features: ['accounts_receivable'],
                            availableParameters: {},
                        },
                        {
                            id: 'payment_statistics',
                            name: 'Payment Statistics',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'late_fees',
                            name: 'Late Fees',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'bad_debt',
                            name: 'Bad Debt',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'promise_to_pays',
                            name: 'Promise-to-Pays',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'communications',
                            name: 'Communications',
                            features: ['accounts_receivable', 'email_sending'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'chasing_activity',
                            name: 'Chasing Activity',
                            features: ['accounts_receivable', 'smart_chasing'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'task_productivity',
                            name: 'Task Productivity',
                            features: ['accounts_receivable', 'smart_chasing'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                    ],
                },
                {
                    title: 'Payments',
                    reports: [
                        {
                            id: 'payment_summary',
                            name: 'Payment Summary',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                                groupBy: [
                                    {
                                        text: 'Customer',
                                        id: 'customer',
                                    },
                                    {
                                        text: 'Method',
                                        id: 'method',
                                    },
                                    {
                                        text: 'Day',
                                        id: 'day',
                                    },
                                    {
                                        text: 'Month',
                                        id: 'month',
                                    },
                                    {
                                        text: 'Quarter',
                                        id: 'quarter',
                                    },
                                    {
                                        text: 'Year',
                                        id: 'year',
                                    },
                                ],
                                defaultGroupBy: 'month',
                                customerCustomFields: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'cash_flow',
                            name: 'Cash Flow Forecast',
                            features: ['accounts_receivable', 'forecasting'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'credit_summary',
                            name: 'Credit Balance Summary',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'expiring_cards',
                            name: 'Expiring Cards',
                            features: ['accounts_receivable'],
                            availableParameters: {},
                        },
                        {
                            id: 'convenience_fees',
                            name: 'Convenience Fees',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'refunds',
                            name: 'Refunds',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'failed_charges',
                            name: 'Failed Charges',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'invoiced_payments_summary',
                            name: 'Invoiced Payments Summary',
                            features: ['accounts_receivable', 'invoiced_payments'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'invoiced_payments_detail',
                            name: 'Invoiced Payments Detail',
                            features: ['accounts_receivable', 'invoiced_payments'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                    ],
                },
                {
                    title: 'Sales',
                    reports: [
                        {
                            id: 'discounts_given',
                            name: 'Discounts Given',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'not_billed_yet',
                            name: 'Not Billed Yet',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                currency: hasCurrency,
                            },
                        },
                        {
                            id: 'sales_summary',
                            name: 'Sales Summary',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                                groupBy: [
                                    {
                                        text: 'Customer',
                                        id: 'customer',
                                    },
                                    {
                                        text: 'Day',
                                        id: 'day',
                                    },
                                    {
                                        text: 'Month',
                                        id: 'month',
                                    },
                                    {
                                        text: 'Quarter',
                                        id: 'quarter',
                                    },
                                    {
                                        text: 'Year',
                                        id: 'year',
                                    },
                                ],
                                defaultGroupBy: 'month',
                                customerCustomFields: true,
                                invoiceCustomFields: true,
                            },
                        },
                        {
                            id: 'sales_by_item',
                            name: 'Sales by Item',
                            features: ['accounts_receivable'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'estimate_summary',
                            name: 'Estimate Summary',
                            features: ['accounts_receivable', 'estimates'],
                            availableParameters: {
                                dateRange: true,
                                currency: hasCurrency,
                            },
                        },
                    ],
                },
                {
                    title: 'Subscriptions',
                    reports: [
                        {
                            id: 'mrr',
                            name: 'Monthly Recurring Revenue',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                                groupBy: [
                                    {
                                        text: 'Month',
                                        id: 'month',
                                    },
                                    {
                                        text: 'Customer',
                                        id: 'customer',
                                    },
                                    {
                                        text: 'Plan',
                                        id: 'plan',
                                    },
                                ],
                                defaultGroupBy: 'month',
                            },
                        },
                        {
                            id: 'arr',
                            name: 'Annual Recurring Revenue',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                                groupBy: [
                                    {
                                        text: 'Month',
                                        id: 'month',
                                    },
                                    {
                                        text: 'Customer',
                                        id: 'customer',
                                    },
                                    {
                                        text: 'Plan',
                                        id: 'plan',
                                    },
                                ],
                                defaultGroupBy: 'month',
                            },
                        },
                        {
                            id: 'mrr_movements',
                            name: 'MRR Movements',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                                groupBy: [
                                    {
                                        text: 'Month',
                                        id: 'month',
                                    },
                                    {
                                        text: 'Customer',
                                        id: 'customer',
                                    },
                                    {
                                        text: 'Plan',
                                        id: 'plan',
                                    },
                                ],
                                defaultGroupBy: 'month',
                            },
                        },
                        {
                            id: 'average_sale_price',
                            name: 'Average Sale Price',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'lifetime_value',
                            name: 'Lifetime Value',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'net_revenue_retention',
                            name: 'Net Revenue Retention',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'subscription_churn',
                            name: 'Churn',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                        {
                            id: 'total_subscribers',
                            name: 'Total Subscribers',
                            features: ['accounts_receivable', 'subscriptions'],
                            availableParameters: {
                                dateRange: true,
                            },
                        },
                    ],
                },
            ];

            let filteredCategories = [];
            angular.forEach(reports, function (category) {
                let filteredReports = [];
                angular.forEach(category.reports, function (report) {
                    if (typeof report.features === 'undefined' || Feature.hasAllFeatures(report.features)) {
                        filteredReports.push(report);
                    }
                });
                category.reports = filteredReports;
                if (category.reports.length > 0) {
                    filteredCategories.push(category);
                }
            });

            selectedCompany.reports = filteredCategories;
            return filteredCategories;
        }

        function getReport(id) {
            let allReports = getAllReports();
            for (let i in allReports) {
                for (let j in allReports[i].reports) {
                    if (allReports[i].reports[j].id === id) {
                        return allReports[i].reports[j];
                    }
                }
            }

            return null;
        }
    }
})();
