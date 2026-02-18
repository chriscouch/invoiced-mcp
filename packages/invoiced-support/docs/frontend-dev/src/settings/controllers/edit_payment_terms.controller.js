(function () {
    'use strict';

    angular.module('app.settings').controller('EditPaymentTermsController', EditPaymentTermsController);

    EditPaymentTermsController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'PaymentTerms', 'paymentTerm'];

    function EditPaymentTermsController($scope, $modalInstance, selectedCompany, PaymentTerms, paymentTerm) {
        if (paymentTerm) {
            $scope.paymentTerm = angular.copy(paymentTerm);
            $scope.paymentTerm.discount_is_percent = true;
            if (typeof $scope.paymentTerm.id === 'undefined') {
                populateFromName($scope.paymentTerm.name);
            }
        } else {
            $scope.paymentTerm = {
                name: '',
                due_in_days: null,
                discount_is_percent: true,
                discount_value: null,
                discount_expires_in_days: null,
                active: true,
            };
        }

        $scope.currency = selectedCompany.currency;
        $scope.hasEarlyDiscount =
            typeof $scope.paymentTerm.discount_expires_in_days !== 'undefined' &&
            $scope.paymentTerm.discount_expires_in_days !== null;

        $scope.save = function (paymentTerm, hasEarlyDiscount) {
            $scope.saving = true;

            let params = {
                name: paymentTerm.name,
                due_in_days: paymentTerm.due_in_days,
                active: paymentTerm.active,
            };

            if (hasEarlyDiscount) {
                params.discount_is_percent = paymentTerm.discount_is_percent;
                params.discount_value = paymentTerm.discount_value;
                params.discount_expires_in_days = paymentTerm.discount_expires_in_days;
            } else {
                params.discount_is_percent = null;
                params.discount_value = null;
                params.discount_expires_in_days = null;
            }

            if (paymentTerm.id !== undefined) {
                PaymentTerms.edit(
                    {
                        id: paymentTerm.id,
                    },
                    params,
                    function (data) {
                        $scope.saving = false;
                        $modalInstance.close(data);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            } else {
                PaymentTerms.create(
                    params,
                    function (data) {
                        $scope.saving = false;
                        $modalInstance.close(data);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        function populateFromName(name) {
            name = name.toString().toLowerCase();
            let dueIn = 0;

            // this checks for an early payment discount
            // i.e. 2% 10 net 30 = 2% discount if paid in 10 days
            let matchesEarlyDiscount = name.match(/([\d]+)% ([\d]+) net ([\d]+)/i);

            // this checks for net D payment terms
            let matchesNetTerms = name.match(/net[\s-]([\d]+)/i);

            if (matchesEarlyDiscount) {
                // calculate the due date
                dueIn = parseInt(matchesEarlyDiscount[3]);
                if (dueIn > 0) {
                    $scope.paymentTerm.due_in_days = dueIn;
                }

                // calculate the early discount
                $scope.paymentTerm.discount_value = parseInt(matchesEarlyDiscount[1]);
                $scope.paymentTerm.discount_is_percent = true;
                $scope.paymentTerm.discount_expires_in_days = parseInt(matchesEarlyDiscount[2]);
            } else if (matchesNetTerms) {
                // calculate the due date
                dueIn = parseInt(matchesNetTerms[1]);
                if (dueIn > 0) {
                    $scope.paymentTerm.due_in_days = dueIn;
                }
            }
        }
    }
})();
