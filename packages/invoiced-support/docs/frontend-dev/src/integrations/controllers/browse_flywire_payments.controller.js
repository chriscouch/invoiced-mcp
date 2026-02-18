(function () {
    'use strict';

    angular.module('app.integrations').controller('BrowseFlywirePaymentsController', BrowseFlywirePaymentsController);

    BrowseFlywirePaymentsController.$inject = ['$scope', '$state', 'TableView', 'UiFilterService', 'Flywire'];

    function BrowseFlywirePaymentsController($scope, $state, TableView, UiFilterService, Flywire) {
        $scope.table = new TableView({
            modelType: 'flywire_payment',
            titlePlural: 'Payments',
            titleSingular: 'Payment',
            icon: '/img/event-icons/payment.png',
            defaultSort: 'initiated_at DESC',
            exportable: true,
            findAllMethod: Flywire.findAllPayments,
            buildRequest: function (table) {
                return {
                    advanced_filter: UiFilterService.serializeFilter(table.filter, table.filterFields),
                    sort: table.sort,
                };
            },
            clickRow: function (payment) {
                if (payment.ar_payment) {
                    $state.go('manage.payment.view.summary', { id: payment.ar_payment });
                }
            },
            columns: [
                {
                    id: 'amount_from',
                    name: 'Amount From',
                    type: 'money',
                    currencyField: 'currency_from',
                    default: true,
                    defaultOrder: 6,
                },
                {
                    id: 'amount_to',
                    name: 'Amount To',
                    type: 'money',
                    currencyField: 'currency_to',
                    default: true,
                    defaultOrder: 7,
                },
                {
                    id: 'cancellation_reason',
                    name: 'Cancellation Reason',
                    type: 'string',
                },
                {
                    id: 'created_at',
                    name: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'currency_from',
                    name: 'Currency From',
                    type: 'string',
                },
                {
                    id: 'currency_to',
                    name: 'Currency To',
                    type: 'string',
                },
                {
                    id: 'expiration_date',
                    name: 'Expiration Date',
                    type: 'datetime',
                },
                {
                    id: 'id',
                    name: 'ID',
                    type: 'string',
                },
                {
                    id: 'initiated_at',
                    name: 'Initiated At',
                    type: 'datetime',
                    default: true,
                    defaultOrder: 1,
                },
                {
                    id: 'payment_id',
                    name: 'Payment ID',
                    type: 'string',
                    default: true,
                    defaultOrder: 2,
                },
                {
                    id: 'payment_method_brand',
                    name: 'Payment Method Brand',
                    type: 'string',
                },
                {
                    id: 'payment_method_card_classification',
                    name: 'Payment Method Card Classification',
                    type: 'string',
                },
                {
                    id: 'payment_method_card_expiration',
                    name: 'Payment Method Card Expiration',
                    type: 'string',
                },
                {
                    id: 'payment_method_last4',
                    name: 'Payment Method Last4',
                    type: 'string',
                },
                {
                    id: 'payment_method_type',
                    name: 'Payment Method Type',
                    type: 'string',
                    default: true,
                    defaultOrder: 3,
                },
                {
                    id: 'reason',
                    name: 'Reason',
                    type: 'string',
                },
                {
                    id: 'reason_code',
                    name: 'Reason Code',
                    type: 'string',
                },
                {
                    id: 'recipient_id',
                    name: 'Portal Code',
                    type: 'string',
                },
                {
                    id: 'status',
                    name: 'Status',
                    type: 'enum',
                    default: true,
                    defaultOrder: 4,
                    values: [
                        {
                            value: 'initiated',
                            text: 'Initiated',
                        },
                        {
                            value: 'processed',
                            text: 'Processed',
                        },
                        {
                            value: 'guaranteed',
                            text: 'Guaranteed',
                        },
                        {
                            value: 'delivered',
                            text: 'Delivered',
                        },
                        {
                            value: 'failed',
                            text: 'Failed',
                        },
                        {
                            value: 'canceled',
                            text: 'Canceled',
                        },
                        {
                            value: 'reversed',
                            text: 'Reversed',
                        },
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
