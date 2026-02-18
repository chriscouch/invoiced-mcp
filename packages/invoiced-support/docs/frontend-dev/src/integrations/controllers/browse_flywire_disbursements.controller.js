(function () {
    'use strict';

    angular
        .module('app.integrations')
        .controller('BrowseFlywireDisbursementsController', BrowseFlywireDisbursementsController);

    BrowseFlywireDisbursementsController.$inject = [
        '$scope',
        '$state',
        'TableView',
        'UiFilterService',
        'Flywire',
        'Money',
    ];

    function BrowseFlywireDisbursementsController($scope, $state, TableView, UiFilterService, Flywire) {
        $scope.table = new TableView({
            modelType: 'flywire_disbursement',
            titlePlural: 'Disbursements',
            titleSingular: 'Disbursement',
            defaultSort: 'delivered_at DESC',
            titleMenu: [
                {
                    title: 'Disbursements',
                    route: 'manage.flywire_disbursements.browse',
                    allFeatures: ['flywire'],
                },
                {
                    title: 'Payments',
                    route: 'manage.payments.browse',
                },
                {
                    title: 'Payment Batches',
                    route: 'manage.customer_payment_batches.browse',
                    allFeatures: ['direct_ach'],
                },
                {
                    title: 'Remittance Advice',
                    route: 'manage.remittance_advices.browse',
                },
                {
                    title: 'Transactions',
                    route: 'manage.transactions.browse',
                },
            ],
            exportable: true,
            findAllMethod: Flywire.findAllDisbursements,
            buildRequest: function (table) {
                return {
                    advanced_filter: UiFilterService.serializeFilter(table.filter, table.filterFields),
                    sort: table.sort,
                };
            },
            clickRow: function (disbursement) {
                $state.go('manage.flywire_disbursement.view', { id: disbursement.id });
            },
            columns: [
                {
                    id: 'amount',
                    name: 'Amount',
                    type: 'money',
                    currencyField: 'currency',
                    default: true,
                    defaultOrder: 5,
                },
                {
                    id: 'bank_account_number',
                    name: 'Bank Account Number',
                    type: 'string',
                    default: true,
                    defaultOrder: 3,
                },
                {
                    id: 'created_at',
                    name: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'currency',
                    name: 'Currency',
                    type: 'currency',
                },
                {
                    id: 'delivered_at',
                    name: 'Delivered At',
                    type: 'datetime',
                    default: true,
                    defaultOrder: 2,
                },
                {
                    id: 'disbursement_id',
                    name: 'Disbursement ID',
                    type: 'string',
                    default: true,
                    defaultOrder: 1,
                },
                {
                    id: 'id',
                    name: 'ID',
                    type: 'string',
                },
                {
                    id: 'recipient_id',
                    name: 'Portal Code',
                    type: 'string',
                },
                {
                    id: 'status_text',
                    name: 'Status',
                    type: 'enum',
                    default: true,
                    defaultOrder: 4,
                    values: [
                        { text: 'Delivered', value: 'delivered' },
                        { text: 'Pending', value: 'pending' },
                    ],
                },
                {
                    id: 'updated_at',
                    name: 'Updated At',
                    type: 'datetime',
                },
            ],
        });
        $scope.table.initialize();
    }
})();
