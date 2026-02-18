(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('BrowsePaymentLinksController', BrowsePaymentLinksController);

    BrowsePaymentLinksController.$inject = [
        '$scope',
        '$state',
        '$modal',
        'TableView',
        'UiFilterService',
        'PaymentLink',
        'PaymentLinkHelper',
    ];

    function BrowsePaymentLinksController(
        $scope,
        $state,
        $modal,
        TableView,
        UiFilterService,
        PaymentLink,
        PaymentLinkHelper,
    ) {
        $scope.table = new TableView({
            modelType: 'payment_link',
            titlePlural: 'Payment Links',
            titleSingular: 'Payment Link',
            icon: '/img/event-icons/payment_link.png',
            defaultSort: 'id DESC',
            actions: [
                {
                    name: 'New',
                    classes: 'btn btn-success',
                    perform: newPaymentLink,
                },
            ],
            findAllMethod: PaymentLink.findAll,
            buildRequest: function (table) {
                return {
                    advanced_filter: UiFilterService.serializeFilter(table.filter, table.filterFields),
                    sort: table.sort,
                    expand: 'customer',
                    include: 'items',
                };
            },
            transformResult: function (paymentLinks) {
                angular.forEach(paymentLinks, function (paymentLink) {
                    paymentLink.customer = paymentLink.customer ? paymentLink.customer.name : null;
                    paymentLink.price = PaymentLinkHelper.getFormattedPrice(paymentLink);
                    paymentLink.type = paymentLink.reusable ? 'Reusable' : 'Single-Use';
                });

                return paymentLinks;
            },
            clickRow: function (paymentLink) {
                $state.go('manage.payment_link.view.summary', { id: paymentLink.id });
            },
            hoverActions: [
                {
                    name: 'Edit',
                    perform: editPaymentLink,
                    classes: 'btn btn-sm btn-default',
                    icon: 'fas fa-edit',
                    showForRow: function (paymentLink) {
                        return paymentLink.status === 'active';
                    },
                },
                {
                    name: 'URL',
                    perform: openPaymentLink,
                    classes: 'btn btn-sm btn-default',
                    icon: 'fas fa-external-link',
                    showForRow: function (paymentLink) {
                        return !!paymentLink.url;
                    },
                },
            ],
            columns: [
                {
                    id: 'after_completion_url',
                    name: 'After Completion URL',
                    type: 'string',
                },
                {
                    id: 'created_at',
                    name: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'customer',
                    name: 'Customer',
                    type: 'customer',
                    sortId: 'Customers.name',
                    default: true,
                    defaultOrder: 2,
                },
                {
                    id: 'deleted',
                    name: 'Deleted',
                    type: 'boolean',
                },
                {
                    id: 'deleted_at',
                    name: 'Deleted At',
                    type: 'datetime',
                },
                {
                    id: 'id',
                    name: 'ID',
                    type: 'string',
                },
                {
                    id: 'name',
                    name: 'Name',
                    type: 'string',
                    default: true,
                    defaultOrder: 1,
                },
                {
                    id: 'price',
                    name: 'Price',
                    type: 'string',
                    filterable: false,
                    sortable: false,
                    default: true,
                    defaultOrder: 5,
                },
                {
                    id: 'reusable',
                    name: 'Reusable',
                    type: 'boolean',
                },
                {
                    id: 'status',
                    name: 'Status',
                    default: true,
                    defaultOrder: 3,
                    type: 'enum',
                    values: [
                        {
                            value: 'active',
                            text: 'Active',
                            class: 'label label-default',
                        },
                        {
                            value: 'completed',
                            text: 'Completed',
                            class: 'label label-success',
                        },
                        {
                            value: 'deleted',
                            text: 'Deleted',
                            class: 'label label-danger',
                        },
                    ],
                },
                {
                    id: 'type',
                    name: 'Type',
                    type: 'string',
                    filterable: false,
                    sortable: false,
                    default: true,
                    defaultOrder: 4,
                },
                {
                    id: 'updated_at',
                    name: 'Updated At',
                    type: 'datetime',
                },
            ],
        });
        $scope.table.initialize();

        function newPaymentLink() {
            $state.go('manage.payment_links.new');
        }

        function editPaymentLink(paymentLink) {
            $state.go('manage.payment_link.edit', { id: paymentLink.id });
        }

        function openPaymentLink(paymentLink) {
            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return paymentLink.url;
                    },
                    customer: function () {
                        return null;
                    },
                },
            });
        }
    }
})();
