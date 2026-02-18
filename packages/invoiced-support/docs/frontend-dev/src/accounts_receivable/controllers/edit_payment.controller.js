/* globals moment, vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('EditPaymentController', EditPaymentController);

    EditPaymentController.$inject = [
        '$scope',
        '$q',
        '$modal',
        '$modalInstance',
        '$timeout',
        'selectedCompany',
        'Payment',
        'File',
        'InvoicedConfig',
        'Money',
        'Core',
        'payment',
        'DatePickerService',
        'CustomField',
        'MetadataCaster',
    ];

    function EditPaymentController(
        $scope,
        $q,
        $modal,
        $modalInstance,
        $timeout,
        selectedCompany,
        Payment,
        File,
        InvoicedConfig,
        Money,
        Core,
        payment,
        DatePickerService,
        CustomField,
        MetadataCaster,
    ) {
        $scope.company = selectedCompany;
        $scope.payment = angular.copy(payment);

        $scope.title = 'Payment';

        $scope.dateOptions = DatePickerService.getOptions();

        $q.all([CustomField.getByObject('payment')]).then(function (data) {
            $scope.customFields = data[0];
        });

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
        };

        $scope.save = function (_payment) {
            $scope.saving = true;
            $scope.error = null;

            let params = {};

            // parse payment
            params.date = moment(_payment.date).unix();
            params.customer = _payment.customer ? parseInt(_payment.customer.id) : null;
            params.method = _payment.method;
            params.reference = _payment.reference;
            params.currency = _payment.currency.toLowerCase();
            params.amount = _payment.amount;
            params.notes = _payment.notes;
            MetadataCaster.marshalForInvoiced('payment', _payment.metadata, function (metadata) {
                params.metadata = metadata;
                let attachments = [];
                angular.forEach(_payment.attachments, function (attachment) {
                    attachments.push(attachment.file.id);
                });
                params.attachments = attachments;

                Payment.edit(
                    {
                        id: _payment.id,
                        include: 'metadata',
                    },
                    params,
                    function (updatedPayment) {
                        $scope.saving = false;

                        // use our locally cached version of these object
                        // because they are not included or expanded in the response
                        updatedPayment.customer = _payment.customer;

                        $modalInstance.close(updatedPayment);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            });
        };

        $scope.addedFiles = function (file) {
            $scope.payment.attachments.push({
                file: file,
            });
        };

        $scope.clearCustomer = function () {
            $scope.payment.customer = null;
        };

        $scope.deleteAttachment = function (attachment) {
            vex.dialog.confirm({
                message: 'Are you sure you want to remove this attachment?',
                callback: function (result) {
                    if (result) {
                        $scope.$apply(function () {
                            deleteAttachment(attachment);
                        });
                    }
                },
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function deleteAttachment(attachment) {
            for (let i in $scope.payment.attachments) {
                if ($scope.payment.attachments[i].file.id === attachment.file.id) {
                    $scope.payment.attachments.splice(i, 1);
                    break;
                }
            }
        }
    }
})();
