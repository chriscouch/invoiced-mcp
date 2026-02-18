(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('AddPaymentSourceController', AddPaymentSourceController);

    AddPaymentSourceController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'Customer',
        'PaymentMethod',
        'Core',
        'PaymentTokens',
        'selectedCompany',
        'customer',
        'options',
    ];

    function AddPaymentSourceController(
        $scope,
        $modal,
        $modalInstance,
        Customer,
        PaymentMethod,
        Core,
        PaymentTokens,
        selectedCompany,
        customer,
        options,
    ) {
        $scope.customer = customer;
        $scope.company = selectedCompany;
        options = angular.extend(
            {
                makeDefault: true,
                returnToken: false,
            },
            options,
        );
        $scope.options = options;
        $scope.makeDefault = false;

        $scope.type = 'credit_card';
        $scope.card = {};
        $scope.bankAccount = {
            country: 'US',
            currency: 'usd',
            account_holder_type: 'individual',
            account_holder_name: null,
            account_number: '',
            routing_number: '',
            type: 'checking',
        };

        // prefill billing info, when available
        if (customer) {
            $scope.card.name = customer.name;
            $scope.card.address_line1 = customer.address1;
            $scope.card.address_line2 = null;
            $scope.card.address_city = customer.city;
            $scope.card.address_state = customer.state;
            $scope.card.address_zip = customer.postal_code;
            $scope.card.address_country = customer.country;

            $scope.bankAccount.account_holder_name = customer.name;

            if (customer.type === 'company') {
                $scope.bankAccount.account_holder_type = 'company';
            }
        }

        $scope.add = function (type) {
            $scope.saving = true;
            $scope.error = null;

            if (type == 'credit_card') {
                let card = angular.copy($scope.card);

                PaymentTokens.tokenizeCard(
                    card,
                    $scope.acceptsCreditCards,
                    function (result) {
                        // success
                        saveToken(customer, result, type);

                        $scope.$apply();
                    },
                    function (message) {
                        // error
                        $scope.saving = false;
                        $scope.error = {
                            message: message,
                        };

                        $scope.$apply();
                    },
                );
            } else if (type == 'ach') {
                let bankAccount = angular.copy($scope.bankAccount);

                PaymentTokens.tokenizeBankAccount(
                    bankAccount,
                    $scope.acceptsACH,
                    function (result) {
                        // success
                        saveToken(customer, result, type);

                        $scope.$apply();
                    },
                    function (message) {
                        // error
                        $scope.saving = false;
                        $scope.error = {
                            message: message,
                        };

                        $scope.$apply();
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadPaymentMethods();

        function loadPaymentMethods() {
            $scope.loading = true;

            PaymentMethod.findAll(
                { paginate: 'none' },
                function (paymentMethods) {
                    $scope.loading = false;
                    $scope.acceptsCreditCards = false;
                    $scope.acceptsACH = false;
                    angular.forEach(paymentMethods, function (paymentMethod) {
                        if (paymentMethod.id == 'ach' && paymentMethod.enabled) {
                            $scope.acceptsACH = paymentMethod.gateway;
                        } else if (paymentMethod.id == 'credit_card' && paymentMethod.enabled) {
                            $scope.acceptsCreditCards = paymentMethod.gateway;
                        }
                    });
                    if (!$scope.acceptsCreditCards) {
                        $scope.type = 'ach';
                    }
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function saveToken(customer, result, type) {
            // if requested return the raw token
            if (options.returnToken) {
                result.type = type;
                $modalInstance.close(result);
                return;
            }

            let params = {
                method: type,
                make_default: options.makeDefault,
            };

            if ($scope.makeDefault) {
                params.make_default = $scope.makeDefault;
            }

            if (result.gateway) {
                params.gateway_token = result.id;
            } else {
                params.invoiced_token = result.id;
            }

            Customer.addPaymentSource(
                {
                    id: customer.id,
                },
                params,
                function (paymentSource) {
                    $scope.saving = false;
                    $modalInstance.close(paymentSource);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
