/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('PaymentTermsSettingsController', PaymentTermsSettingsController);

    PaymentTermsSettingsController.$inject = [
        '$scope',
        '$modal',
        'Core',
        'PaymentTerms',
        'Settings',
        'LeavePageWarning',
        'selectedCompany',
    ];

    function PaymentTermsSettingsController(
        $scope,
        $modal,
        Core,
        PaymentTerms,
        Settings,
        LeavePageWarning,
        selectedCompany,
    ) {
        $scope.currency = selectedCompany.currency;

        $scope.edit = function (paymentTerm) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-payment-terms.html',
                controller: 'EditPaymentTermsController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    paymentTerm: function () {
                        return paymentTerm;
                    },
                },
            });

            modalInstance.result.then(
                function (r) {
                    LeavePageWarning.unblock();

                    if (paymentTerm && paymentTerm.id) {
                        Core.flashMessage('Payment term was updated', 'success');
                        angular.extend(paymentTerm, r);
                    } else {
                        Core.flashMessage('Payment term was created', 'success');
                        $scope.paymentTerms.push(r);
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.duplicate = function (paymentTerm) {
            paymentTerm = angular.copy(paymentTerm);
            delete paymentTerm.id;
            $scope.edit(paymentTerm);
        };

        $scope.makeDefault = makeDefault;

        $scope.delete = function (paymentTerm) {
            vex.dialog.confirm({
                message: 'Are you sure you want to remove this payment term?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting = true;
                        PaymentTerms.delete(
                            {
                                id: paymentTerm.id,
                            },
                            function () {
                                PaymentTerms.clearCache();
                                $scope.deleting = false;
                                for (let i in $scope.paymentTerms) {
                                    if ($scope.paymentTerms[i].id === paymentTerm.id) {
                                        $scope.paymentTerms.splice(i, 1);
                                        break;
                                    }
                                }
                                Core.flashMessage('Payment terms were deleted', 'success');
                            },
                            function (result) {
                                $scope.deleting = false;
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

        Core.setTitle('Payment Terms');
        load();

        function load() {
            $scope.loading = true;
            PaymentTerms.findAll(
                {
                    paginate: 'none',
                },
                function (paymentTerms) {
                    $scope.paymentTerms = paymentTerms;
                    $scope.loading = false;
                    // Load default setting
                    Settings.accountsReceivable(function (settings) {
                        angular.forEach($scope.paymentTerms, function (paymentTerm) {
                            paymentTerm.default = settings.payment_terms === paymentTerm.name;
                        });
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.loading = false;
                },
            );
        }
        function makeDefault(paymentTerm, isDefault) {
            $scope.saving = true;
            $scope.error = null;

            Settings.editAccountsReceivable(
                {
                    payment_terms: isDefault ? paymentTerm.name : null,
                },
                function (settings) {
                    $scope.saving = false;
                    angular.forEach($scope.paymentTerms, function (paymentTerm) {
                        paymentTerm.default = settings.payment_terms === paymentTerm.name;
                    });
                    Core.flashMessage('Your settings have been updated.', 'success');
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
