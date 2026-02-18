/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('ViewVendorPaymentController', ViewVendorPaymentController);

    ViewVendorPaymentController.$inject = [
        '$scope',
        '$state',
        '$controller',
        '$modal',
        '$rootScope',
        'LeavePageWarning',
        'VendorPayment',
        'Core',
        'BrowsingHistory',
        'ECheck',
        'PaymentDisplayHelper',
    ];

    function ViewVendorPaymentController(
        $scope,
        $state,
        $controller,
        $modal,
        $rootScope,
        LeavePageWarning,
        VendorPayment,
        Core,
        BrowsingHistory,
        ECheck,
        PaymentDisplayHelper,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = VendorPayment;
        $scope.modelTitleSingular = 'Vendor Payment';
        $scope.modelObjectType = 'vendor_payment';
        $scope.attachments = [];
        $scope.printable = false;

        //
        // Presets
        //

        $scope.documents = [];
        $scope.documentPage = 1;

        //
        // Methods
        //

        $scope.canResendCheck = false;

        $scope.preFind = function (findParams) {
            findParams.expand = 'vendor,vendor_payment_batch,bank_account,card';
            findParams.include = 'applied_to';
        };

        $scope.postFind = function (payment) {
            $scope.payment = payment;

            if (payment.card) {
                payment.card.name = PaymentDisplayHelper.formatCard(payment.card.brand, payment.card.last4);
            }

            $rootScope.modelTitle = payment.number;
            Core.setTitle(payment.number);

            BrowsingHistory.push({
                id: payment.id,
                type: 'vendor_payment',
                title: payment.number,
            });

            VendorPayment.attachments(
                {
                    id: payment.id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                },
            );

            if (payment.payment_method === 'check' && !payment.voided) {
                $scope.printable = true;
            }

            return $scope.payment;
        };

        $scope.e_check = null;
        ECheck.list(
            {
                'filter[payment_id]': $state.params.id,
            },
            function (result) {
                if (result.length) {
                    $scope.e_check = result[0];
                    $scope.canResendCheck = $scope.e_check.created_at > new Date().getTime() / 1000 - 86400 * 90;
                }
            },
            function (result) {
                Core.showMessage(result.data.message, 'error');
            },
        );

        $scope.resendCheck = function () {
            ECheck.send(
                {
                    id: $scope.e_check.id,
                },
                {},
                function () {
                    Core.showMessage('Check has been resent.', 'success');
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.editModal = function (payment) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/payments/edit.html',
                controller: 'EditVendorPaymentController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    payment: function () {
                        return payment;
                    },
                },
            });

            modalInstance.result.then(
                function (c) {
                    LeavePageWarning.unblock();

                    angular.extend(payment, c);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.print = function (payment) {
            $scope.printing = true;
            VendorPayment.printCheck(
                payment,
                function () {
                    $scope.printing = false;
                },
                function (error) {
                    $scope.printing = false;
                    Core.flashMessage(error.message, 'error');
                },
            );
        };

        $scope.delete = function (payment) {
            vex.dialog.confirm({
                message: 'Are you sure you want to void this payment? This operation is irreversible.',
                callback: function (result) {
                    if (result) {
                        VendorPayment.delete(
                            {
                                id: payment.id,
                            },
                            function () {
                                payment.voided = true;
                                $scope.printable = false;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Vendor Payment');
    }
})();
