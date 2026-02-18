(function () {
    'use strict';

    angular.module('app.settings').factory('PlaidLinkService', PlaidLinkService);

    PlaidLinkService.$inject = ['$resource', '$window', 'InvoicedConfig', 'selectedCompany'];

    function PlaidLinkService($resource, $window, InvoicedConfig, selectedCompany) {
        let resource = $resource(
            InvoicedConfig.apiBaseUrl + '/plaid_links/:id',
            {
                id: '@id',
            },
            {
                createLinkToken: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/integrations/plaid/create_link_token',
                },
                createUpgradeLinkToken: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/plaid_links/:id/create_link_token',
                },
                verificationFinish: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/plaid_links/:id/verification',
                },
            },
        );

        let dataCreate = {
            user: {
                client_user_id: 'tenant:' + selectedCompany.id,
            },
            country_codes: [selectedCompany.country],
            products: ['auth'],
            language: selectedCompany.language,
            auth: {
                same_day_microdeposits_enabled: true,
            },
        };

        let dataVerify = {
            user: {
                client_user_id: 'tenant:' + selectedCompany.id,
            },
            country_codes: [selectedCompany.country],
            language: selectedCompany.language,
        };

        resource.createToken = function (success, exit) {
            resource.createLinkToken(
                dataCreate,
                function (result) {
                    resource.openPlaid(result.link_token, success, exit);
                },
                function (result) {
                    exit(result.data.message);
                },
            );
        };

        resource.upgradeToken = function (id, success, exit) {
            resource.createUpgradeLinkToken(
                { id: id },
                dataVerify,
                function (result) {
                    resource.openPlaid(result.link_token, success, exit);
                },
                function (result) {
                    exit(result.data.message);
                },
            );
        };

        resource.openPlaid = function (linkToken, successCb, exitCb) {
            let handler = $window.Plaid.create({
                token: linkToken,
                onSuccess: successCb,
                onExit: function (err) {
                    let error = null;
                    if (err != null) {
                        // The user encountered a Plaid API error prior to exiting.
                        error = parsePlaidError(err);
                        if (!error) {
                            error =
                                'We had trouble communicating with Plaid. Please try again or contact Invoiced Support.';
                        }
                    }

                    exitCb(error);
                },
            });

            handler.open();
        };

        function parsePlaidError(error) {
            if (error.display_message) {
                return error.display_message;
            }

            // check for unsupported country
            if (-1 < error.error_message.indexOf('country')) {
                return 'Your country is not supported.';
            }

            return null;
        }

        return resource;
    }
})();
