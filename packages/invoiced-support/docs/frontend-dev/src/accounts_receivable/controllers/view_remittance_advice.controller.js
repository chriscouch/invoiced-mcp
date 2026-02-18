(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('ViewRemittanceAdviceController', ViewRemittanceAdviceController);

    ViewRemittanceAdviceController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$modal',
        '$filter',
        '$timeout',
        'LeavePageWarning',
        'RemittanceAdvice',
        'Money',
        'Core',
        'InvoicedConfig',
        'File',
        'Transaction',
        'Feature',
        'BrowsingHistory',
        'Estimate',
        'Invoice',
        'CreditNote',
        'Attachment',
    ];

    function ViewRemittanceAdviceController(
        $scope,
        $state,
        $controller,
        $modal,
        $filter,
        $timeout,
        LeavePageWarning,
        RemittanceAdvice,
        Money,
        Core,
        InvoicedConfig,
        File,
        Transaction,
        Feature,
        BrowsingHistory,
        Estimate,
        Invoice,
        CreditNote,
        Attachment,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = RemittanceAdvice;
        $scope.modelTitleSingular = 'Remittance Advice';
        $scope.modelObjectType = 'remittance_advice';
        $scope.appliedTo = [];

        //
        // Presets
        //

        $scope.tab = 'summary';

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer';
        };

        $scope.postFind = function (remittanceAdvice) {
            $scope.remittanceAdvice = remittanceAdvice;
            loadAttachments(remittanceAdvice.id);
            if (!$scope.remittanceAdvice.attachments) {
                $scope.remittanceAdvice.attachments = [];
            }

            BrowsingHistory.push({
                id: remittanceAdvice.id,
                type: 'remittance_advice',
                title:
                    (remittanceAdvice.customer ? remittanceAdvice.customer.name : 'Payment') +
                    ': ' +
                    Money.currencyFormat(
                        remittanceAdvice.total_net_amount_paid,
                        remittanceAdvice.currency,
                        $scope.company.moneyFormat,
                    ),
            });

            return $scope.remittanceAdvice;
        };

        $scope.resolveLine = function (remittanceAdvice, line) {
            $scope.resolving = true;
            RemittanceAdvice.resolveLine(
                {
                    id: remittanceAdvice.id,
                    lineId: line.id,
                },
                {},
                function () {
                    $scope.resolving = false;
                    // reload
                    $scope.find(remittanceAdvice.id);
                    $scope.loading = false;
                },
                function (result) {
                    $scope.resolving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.postPayment = function (remittanceAdvice) {
            $scope.posting = true;
            RemittanceAdvice.postPayment(
                {
                    id: remittanceAdvice.id,
                },
                {},
                function () {
                    $scope.posting = false;
                    // reload
                    $scope.find(remittanceAdvice.id);
                },
                function (result) {
                    $scope.posting = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.addedFiles = function (files) {
            // create the file objects on Invoiced
            angular.forEach(files, function (_file) {
                File.create(_file).then(function (file) {
                    // attach to the document
                    $scope.remittanceAdvice.attachments.push({
                        file: file,
                    });

                    Attachment.create(
                        {
                            parent_type: 'remittance_advice',
                            parent_id: $scope.remittanceAdvice.id,
                            file_id: file.id,
                            location: 'attachment',
                        },
                        function () {
                            // do nothing
                        },
                        function (result) {
                            $scope.error = result.data;
                        },
                    );
                });
            });
        };

        $scope.isImage = function (file) {
            return file.type.split('/')[0] === 'image' && file.url;
        };

        // cancel any reload promises after leaving the page
        $scope.$on('$stateChangeStart', function () {
            if ($scope.reload) {
                $timeout.cancel($scope.reload);
            }
        });

        function loadAttachments(id) {
            RemittanceAdvice.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                    $scope.remittanceAdvice.attachments = attachments;
                },
            );
        }

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Remittance Advice');
    }
})();
