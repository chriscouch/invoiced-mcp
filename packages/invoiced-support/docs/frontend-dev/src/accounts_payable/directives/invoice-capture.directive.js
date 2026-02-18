(function () {
    'use strict';

    angular.module('app.accounts_payable').directive('invoiceCapture', invoiceCapture);

    function invoiceCapture() {
        return {
            restrict: 'E',
            template:
                '<span>' +
                '<a href="" class="btn btn-default hidden-xs hidden-sm" ng-click="pickFiles()" tabindex="2005">Invoice Capture</a>' +
                '<filePond' +
                '                            callback="callback"' +
                '                            drop-on-page="false"' +
                '                            types="types"' +
                '                            oninitfile="oninitfile"' +
                '                            style="display:none"></filePond>' +
                '</span>',
            scope: {
                document: '=',
            },
            replace: true,
            controller: [
                '$scope',
                '$modal',
                '$state',
                'LeavePageWarning',
                'Core',
                'InvoiceCapture',
                function ($scope, $modal, $state, LeavePageWarning, Core, InvoiceCapture) {
                    $scope.pickFiles = pickFiles;
                    $scope.callback = callback;
                    $scope.oninitfile = oninitfile;

                    $scope.types = [
                        'image/jpeg',
                        'image/png',
                        'image/jpg',
                        'image/gif',
                        'image/tiff',
                        'application/pdf',
                    ];

                    function oninitfile() {
                        $scope.$parent.loading = 1;
                    }
                    function pickFiles() {
                        $('.filepond--browser').click();
                    }

                    function callback(file) {
                        $scope.disabled = false;
                        InvoiceCapture.import(
                            {
                                file_id: file.id,
                                vendor_id: $scope.document.vendor ? $scope.document.vendor.id : null,
                            },
                            verifyStatus,
                            function () {
                                $scope.$parent.loading = 0;
                                LeavePageWarning.unblock();
                                Core.showMessage('Error while uploading file', 'error');
                            },
                        );
                    }

                    function verifyStatus(input) {
                        //30 seconds limit
                        if (!$scope.limit) {
                            $scope.limit = new Date().getTime();
                        }

                        if (
                            $state.current.name !== 'manage.bills.new' &&
                            $state.current.name !== 'manage.vendor_credits.new'
                        ) {
                            return;
                        }

                        InvoiceCapture.completed(
                            {
                                id: input.id,
                            },
                            function (data) {
                                //not completed
                                if (data.status < 4) {
                                    setTimeout(function () {
                                        verifyStatus(input);
                                    }, 500);

                                    return;
                                }

                                if (data.status === 5) {
                                    $scope.$parent.loading = 0;
                                    LeavePageWarning.unblock();
                                    Core.showMessage('Error while analyzing the document', 'error');
                                    return;
                                }

                                $scope.$parent.loading = 0;

                                $scope.document.number = data.data.number;
                                $scope.document.date = data.data.date;
                                $scope.document.line_items = data.data.line_items;
                                $scope.document.currency = data.data.currency;
                                $scope.document.import_id = data.id;

                                if (data.vendor) {
                                    $scope.document.vendor = data.vendor;
                                } else {
                                    const modalInstance = $modal.open({
                                        templateUrl: 'accounts_payable/views/vendors/edit.html',
                                        controller: 'EditVendorController',
                                        backdrop: 'static',
                                        keyboard: false,
                                        size: 'lg',
                                        resolve: {
                                            model: function () {
                                                return {
                                                    name: data.data.vendor,
                                                    address1: data.data.address1,
                                                    city: data.data.city,
                                                    state: data.data.state,
                                                    postal_code: data.data.postal_code,
                                                    country: data.data.country,
                                                };
                                            },
                                        },
                                    });

                                    modalInstance.result.then(
                                        function (vendor) {
                                            $scope.document.vendor = vendor;
                                        },
                                        function () {},
                                    );
                                }

                                LeavePageWarning.unblock();
                            },
                            function () {
                                $scope.$parent.loading = 0;
                                LeavePageWarning.unblock();
                                Core.showMessage('Error while retrieving created document', 'error');
                            },
                        );
                    }
                },
            ],
        };
    }
})();
