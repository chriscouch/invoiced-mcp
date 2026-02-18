(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('BrowseRemittanceAdviceController', BrowseRemittanceAdviceController);

    BrowseRemittanceAdviceController.$inject = [
        '$scope',
        '$state',
        '$translate',
        'TableView',
        'UiFilterService',
        'InvoicedConfig',
        'Core',
        'RemittanceAdvice',
    ];

    function BrowseRemittanceAdviceController(
        $scope,
        $state,
        $translate,
        TableView,
        UiFilterService,
        InvoicedConfig,
        Core,
        RemittanceAdvice,
    ) {
        $scope.uploading = 0;
        $scope.uploadActionLabel = 'Upload';
        $scope.table = new TableView({
            modelType: 'remittance_advice',
            titlePlural: 'Remittance Advice',
            titleSingular: 'Remittance Advice',
            icon: '/img/event-icons/payment.png',
            defaultSort: 'payment_date DESC',
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
            actions: [
                {
                    name: 'Import',
                    classes: 'btn btn-default hidden-xs hidden-sm',
                    allPermissions: ['imports.create', 'payments.create'],
                    perform: function () {
                        $state.go('manage.imports.new.spreadsheet', { type: 'remittance_advice' });
                    },
                },
                {
                    nameFunction: () => $scope.uploadActionLabel,
                    classes: 'btn btn-success filepond',
                    allPermissions: ['payments.create'],
                    perform: uploadRemittanceAdvice,
                    directive: 'filePond',
                    dropOnPage: false,
                    allowMultiple: false,
                    callback: filesUploaded,
                    oninitfile: oninitfile,
                    types: ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/tiff', 'application/pdf'],
                },
            ],
            findAllMethod: RemittanceAdvice.findAll,
            buildRequest: function (table) {
                return {
                    advanced_filter: UiFilterService.serializeFilter(table.filter, table.filterFields),
                    expand: 'customer',
                    exclude: 'lines',
                    sort: table.sort,
                };
            },
            transformResult: function (advices) {
                angular.forEach(advices, function (advice) {
                    advice.customer = advice.customer ? advice.customer.name : null;
                });

                return advices;
            },
            clickRow: function (advice) {
                $state.go('manage.remittance_advice.view.summary', { id: advice.id });
            },
            columns: [
                {
                    id: 'created_at',
                    name: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'currency',
                    name: 'Currency',
                    type: 'enum',
                    values: UiFilterService.getCurrencyChoices(),
                },
                {
                    id: 'customer',
                    name: 'Customer',
                    type: 'customer',
                    sortable: false,
                    default: true,
                    defaultOrder: 2,
                },
                {
                    id: 'id',
                    name: 'ID',
                    type: 'string',
                },
                {
                    id: 'notes',
                    name: 'Notes',
                    type: 'string',
                },
                {
                    id: 'payment_date',
                    name: 'Payment Date',
                    type: 'date',
                    default: true,
                    defaultOrder: 1,
                },
                {
                    id: 'payment_method',
                    name: 'Payment Method',
                    default: true,
                    defaultOrder: 4,
                    type: 'enum',
                    values: [
                        { value: 'ach', text: $translate.instant('payment_method.ach') },
                        { value: 'balance', text: $translate.instant('payment_method.balance') },
                        { value: 'bank_transfer', text: $translate.instant('payment_method.bank_transfer') },
                        { value: 'cash', text: $translate.instant('payment_method.cash') },
                        { value: 'check', text: $translate.instant('payment_method.check') },
                        { value: 'credit_card', text: $translate.instant('payment_method.credit_card') },
                        { value: 'direct_debit', text: $translate.instant('payment_method.direct_debit') },
                        { value: 'eft', text: $translate.instant('payment_method.eft') },
                        { value: 'online', text: $translate.instant('payment_method.online') },
                        { value: 'other', text: $translate.instant('payment_method.other') },
                        { value: 'paypal', text: $translate.instant('payment_method.paypal') },
                        { value: 'wire_transfer', text: $translate.instant('payment_method.wire_transfer') },
                    ],
                },
                {
                    id: 'payment_reference',
                    name: 'Payment Reference',
                    type: 'string',
                    default: true,
                    defaultOrder: 5,
                },
                {
                    id: 'status',
                    name: 'Status',
                    default: true,
                    defaultOrder: 6,
                    type: 'enum',
                    values: [
                        {
                            value: 'ReadyToPost',
                            text: 'Ready To Post',
                        },
                        {
                            value: 'Exception',
                            text: 'Exception',
                        },
                        {
                            value: 'Posted',
                            text: 'Posted',
                        },
                    ],
                },
                {
                    id: 'total_discount',
                    name: 'Total Discount',
                    type: 'money',
                },
                {
                    id: 'total_gross_amount_paid',
                    name: 'Total Gross Amount Paid',
                    type: 'money',
                },
                {
                    id: 'total_net_amount_paid',
                    name: 'Total Net Amount Paid',
                    type: 'money',
                    default: true,
                    defaultOrder: 7,
                },
                {
                    id: 'updated_at',
                    name: 'Updated At',
                    type: 'datetime',
                },
            ],
        });
        $scope.table.initialize();

        function uploadRemittanceAdvice() {
            $('.filepond--browser').click();
        }

        function oninitfile() {
            if (!$scope.uploading) {
                Core.flashMessage(
                    'Your remittance advice is being uploaded and processed. It will appear in the list once processing is complete.',
                    'success',
                );
            }
            $scope.uploadActionLabel = 'Uploading...';
            ++$scope.uploading;
        }

        function filesUploaded(file) {
            RemittanceAdvice.upload(
                {
                    file: file.id,
                },
                function () {
                    $scope.uploading--;
                    if ($scope.uploading === 0) {
                        $scope.uploadActionLabel = 'Upload';
                    }
                    Core.flashMessage('Your remittance advice has been uploaded.', 'success');
                    $scope.table.findAll();
                },
                function (result) {
                    $scope.uploading--;
                    if ($scope.uploading === 0) {
                        $scope.uploadActionLabel = 'Upload';
                    }
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
