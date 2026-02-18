(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('files', files);

    function files() {
        return {
            restrict: 'E',
            templateUrl: 'accounts_receivable/views/directives/files.html',
            scope: {
                document: '=',
                type: '@',
                attachments: '=',
                allowUpload: '=?',
                allowMultiple: '=?',
            },
            controller: [
                '$scope',
                '$translate',
                'Attachment',
                'InvoicedConfig',
                'Core',
                'Customer',
                'File',
                'VendorPayment',
                'Bill',
                'VendorCredit',
                'Company',
                function (
                    $scope,
                    $translate,
                    Attachment,
                    InvoicedConfig,
                    Core,
                    Customer,
                    File,
                    VendorPayment,
                    Bill,
                    VendorCredit,
                    Company,
                ) {
                    $scope.allowMultiple = typeof $scope.allowMultiple === 'undefined' ? true : !!$scope.allowMultiple;
                    $scope.allowUpload = typeof $scope.allowUpload === 'undefined' ? true : !!$scope.allowUpload;
                    $scope.canDelete = $scope.type === 'customer' || $scope.type === 'company';
                    $scope.addedFiles = function (file) {
                        // attach to the document
                        $scope.attachments.push({
                            file: file,
                        });

                        var attachmentObject = null;
                        switch ($scope.type) {
                            case 'vendor_payment':
                                attachmentObject = VendorPayment;
                                break;
                            case 'bill':
                                attachmentObject = Bill;
                                break;
                            case 'vendor_credit':
                                attachmentObject = VendorCredit;
                                break;
                            case 'company':
                                attachmentObject = Company;
                                break;
                        }

                        if (attachmentObject) {
                            attachmentObject.attach(
                                {
                                    id: $scope.document.id,
                                },
                                {
                                    file_id: file.id,
                                },
                                function () {
                                    // do nothing
                                },
                                function (result) {
                                    $scope.error = result.data;
                                },
                            );
                            return;
                        }

                        Attachment.create(
                            {
                                parent_type: $scope.type,
                                parent_id: $scope.document.id,
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
                    };

                    $scope.createAttachment = function () {};

                    $scope.isImage = function (file) {
                        return file.type.split('/')[0] === 'image' && file.url;
                    };

                    $scope.deleteAttachment = function (attachment) {
                        if ($scope.type === 'customer') {
                            Customer.deleteAttachment(
                                {
                                    subid: attachment.file.id,
                                    id: $scope.document.id,
                                },
                                function () {
                                    $scope.attachments = $scope.attachments.filter(function (item) {
                                        return attachment.file.id !== item.file.id;
                                    });
                                },
                                function (result) {
                                    $scope.error = result.data;
                                },
                            );
                            return;
                        }
                        if ($scope.type === 'company') {
                            File.delete(
                                {
                                    id: attachment.file.id,
                                },
                                function () {
                                    $scope.attachments = $scope.attachments.filter(function (item) {
                                        return attachment.file.id !== item.file.id;
                                    });
                                },
                                function (result) {
                                    $scope.error = result.data;
                                },
                            );
                            return;
                        }
                        Core.showMessage($translate.instant('invoices.view.summary.delete_attachment_error'), 'error');
                    };
                },
            ],
        };
    }
})();
