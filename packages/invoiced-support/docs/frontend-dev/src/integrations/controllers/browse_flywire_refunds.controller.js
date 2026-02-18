(function () {
    'use strict';

    angular.module('app.integrations').controller('BrowseFlywireRefundsController', BrowseFlywireRefundsController);

    BrowseFlywireRefundsController.$inject = ['$scope', 'TableView', 'UiFilterService', 'Flywire'];

    function BrowseFlywireRefundsController($scope, TableView, UiFilterService, Flywire) {
        $scope.table = new TableView({
            modelType: 'flywire_refund',
            titlePlural: 'Refunds',
            titleSingular: 'Refund',
            icon: '/img/event-icons/refund.png',
            defaultSort: 'initiated_at DESC',
            exportable: true,
            findAllMethod: Flywire.findAllRefunds,
            buildRequest: function (table) {
                return {
                    advanced_filter: UiFilterService.serializeFilter(table.filter, table.filterFields),
                    sort: table.sort,
                };
            },
            columns: [
                {
                    id: 'amount',
                    name: 'Amount',
                    type: 'money',
                    currencyField: 'currency',
                    default: true,
                    defaultOrder: 3,
                },
                {
                    id: 'amount_to',
                    name: 'Amount To',
                    type: 'money',
                    currencyField: 'currency_to',
                    default: true,
                    defaultOrder: 4,
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
                    id: 'currency_to',
                    name: 'Currency To',
                    type: 'string',
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
                    id: 'recipient_id',
                    name: 'Portal Code',
                    type: 'string',
                },
                {
                    id: 'refund_id',
                    name: 'Refund ID',
                    type: 'string',
                    default: true,
                    defaultOrder: 2,
                },
                {
                    id: 'status',
                    name: 'Status',
                    default: true,
                    defaultOrder: 5,
                    type: 'enum',
                    values: [
                        {
                            value: 'initiated',
                            text: 'Initiated',
                        },
                        {
                            value: 'received',
                            text: 'Received',
                        },
                        {
                            value: 'pending',
                            text: 'Pending',
                        },
                        {
                            value: 'finished',
                            text: 'Finished',
                        },
                        {
                            value: 'returned',
                            text: 'Returned',
                        },
                        {
                            value: 'cancelled',
                            text: 'Canceled',
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
