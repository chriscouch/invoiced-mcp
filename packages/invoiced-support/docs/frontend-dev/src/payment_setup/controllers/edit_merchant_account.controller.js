(function () {
    'use strict';

    angular.module('app.payment_setup').controller('EditMerchantAccountController', EditMerchantAccountController);

    EditMerchantAccountController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'MerchantAccount',
        'InvoicedConfig',
        'gateway',
        'existingAccount',
    ];

    function EditMerchantAccountController(
        $scope,
        $modalInstance,
        selectedCompany,
        MerchantAccount,
        InvoicedConfig,
        gateway,
        existingAccount,
    ) {
        $scope.gateway = gateway;
        $scope.testMode = selectedCompany.test_mode || InvoicedConfig.environment === 'dev';

        $scope.authorizenet = {
            name: 'Authorize.Net account',
            credentials: {
                login_id: '',
                transaction_key: '',
                ach_sec_code: '',
                test_mode: $scope.testMode,
            },
        };
        $scope.braintree = {
            name: 'Braintree account',
            credentials: {
                merchant_id: '',
                merchant_account_ids: [
                    {
                        currency: selectedCompany.currency,
                        id: '',
                    },
                ],
                public_key: '',
                private_key: '',
                test_mode: $scope.testMode,
            },
        };
        $scope.cardknox = {
            name: 'Cardknox account',
            credentials: {
                key: '',
            },
        };
        $scope.cybersource = {
            name: 'CyberSource account',
            credentials: {
                merchant_id: '',
                transaction_key: '',
                ach_sec_code: '',
                currency: selectedCompany.currency,
                test_mode: $scope.testMode,
            },
        };
        $scope.flywire = {
            name: 'Flywire account',
            credentials: {
                flywire_portal_codes: [
                    {
                        currency: selectedCompany.currency,
                        id: '',
                    },
                ],
                shared_secret: '',
                test_mode: $scope.testMode,
            },
        };
        $scope.flywire_payments = {
            name: 'Flywire account',
            credentials: {
                test_mode: $scope.testMode,
            },
        };
        $scope.moneris = {
            name: 'Moneris account',
            credentials: {
                store_id: '',
                api_token: '',
                processing_country: selectedCompany.country,
                test_mode: $scope.testMode,
            },
        };
        $scope.nmi = {
            name: 'NMI account',
            credentials: {
                username: '',
                password: '',
                ach_sec_code: '',
                test_mode: $scope.testMode,
            },
        };
        $scope.orbital = {
            name: 'Chase Paymentech Orbital account',
            credentials: {
                merchant_id: '',
                username: '',
                password: '',
                bin: '000001',
                terminal_id: '',
                test_mode: $scope.testMode,
            },
        };
        $scope.payflowpro = {
            name: 'PayPal Payflow Pro account',
            credentials: {
                partner: '',
                vendor: '',
                user: '',
                password: '',
                ach_sec_code: '',
                test_mode: $scope.testMode,
            },
        };
        $scope.vantiv = {
            name: 'Worldpay account',
            credentials: {
                account_id: '',
                sub_id: '',
                merchant_pin: '',
                test_mode: $scope.testMode,
            },
        };

        let gatewayIdProperties = {
            authorizenet: 'login_id',
            braintree: 'merchant_id',
            cybersource: 'merchant_id',
            flywire_payments: 'merchant_id',
            moneris: 'store_id',
            nmi: 'username',
            orbital: 'merchant_id',
            payflowpro: 'vendor',
            vantiv: 'account_id',
        };

        if (typeof $scope[gateway] !== 'undefined') {
            $scope[gateway].name = existingAccount.name;
            $scope[gateway].credentials = angular.extend($scope[gateway].credentials, existingAccount.credentials);
        }

        $scope.isOauthGateway = isOauthGateway;
        $scope.test = test;
        $scope.save = save;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function isOauthGateway(gateway) {
            return typeof $scope[gateway] === 'undefined';
        }

        function test(gateway, credentials) {
            $scope.testing = true;
            $scope.testError = false;
            $scope.testSuccessful = false;

            MerchantAccount.test(
                {
                    gateway: gateway,
                    credentials: credentials,
                },
                function () {
                    $scope.testing = false;
                    $scope.testSuccessful = true;
                },
                function (result) {
                    $scope.testing = false;
                    $scope.testError = result.data;
                    $scope.testSuccessful = false;
                },
            );
        }

        function save(gateway) {
            $scope.saving = true;
            $scope.error = null;

            let gatewayId = getIdProperty(gateway, $scope[gateway].credentials);
            let params = {
                gateway: gateway,
                gateway_id: gatewayId,
            };

            angular.extend(params, $scope[gateway]);

            if (gateway === 'orbital' && params.credentials.bin === '000001') {
                delete params.credentials.terminal_id;
            }

            MerchantAccount.edit(
                {
                    id: existingAccount.id,
                    include: 'credentials',
                },
                params,
                function (merchantAccount) {
                    $scope.saving = false;
                    $modalInstance.close(merchantAccount);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function getIdProperty(gateway, credentials) {
            if (gateway === 'cardknox') {
                return 'cardknox';
            }

            if (gateway === 'flywire') {
                return credentials.flywire_portal_codes[0].id;
            }

            let idProperty = gatewayIdProperties[gateway];
            return credentials[idProperty];
        }
    }
})();
