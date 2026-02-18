/* globals vex, moment */
(function () {
    'use strict';

    angular
        .module('app.settings')
        .controller('CashApplicationBankAccountsController', CashApplicationBankAccountsController);

    CashApplicationBankAccountsController.$inject = [
        '$scope',
        '$modal',
        '$window',
        '$filter',
        'selectedCompany',
        'Core',
        'InvoicedConfig',
        'PlaidLinkService',
        'CashApplicationBankService',
    ];

    function CashApplicationBankAccountsController(
        $scope,
        $modal,
        $window,
        $filter,
        selectedCompany,
        Core,
        InvoicedConfig,
        PlaidLinkService,
        CashApplicationBankService,
    ) {
        $scope.bankAccounts = [];

        $scope.addBankAccount = function () {
            $scope.openingPlaid = true;
            PlaidLinkService.createLinkToken(
                {
                    user: {
                        client_user_id: 'tenant:' + selectedCompany.id,
                    },
                    country_codes: [selectedCompany.country],
                    products: ['transactions'],
                    webhook: InvoicedConfig.baseUrl + '/plaid/webhook',
                    language: selectedCompany.language,
                    account_filters: {
                        depository: {
                            account_subtypes: ['checking'],
                        },
                    },
                },
                function (result) {
                    openPlaid(result.link_token, null);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.upgradePlaidLink = function (link) {
            $scope.upgradeLoading = true;
            PlaidLinkService.createUpgradeLinkToken(
                { id: link.id },
                {
                    user: {
                        client_user_id: 'tenant:' + selectedCompany.id,
                    },
                    country_codes: [selectedCompany.country],
                    webhook: InvoicedConfig.baseUrl + '/plaid/webhook',
                    language: selectedCompany.language,
                },
                function (result) {
                    openPlaid(result.link_token, link);
                },
                function (result) {
                    $scope.upgradeLoading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.delete = function (account, index) {
            vex.dialog.confirm({
                message: deleteMessage(account.plaid_link),
                callback: function (result) {
                    if (result) {
                        deleteLink(account, index);
                    }
                },
            });
        };

        $scope.pullTransactions = function (link) {
            $modal.open({
                templateUrl: 'settings/views/plaid-date-range.html',
                controller: 'PlaidDateRangeController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    plaidLink: function () {
                        return link;
                    },
                },
            });
        };

        //
        // Initialization
        //

        Core.setTitle('Bank Accounts');

        loadBankLinks();

        function loadBankLinks() {
            CashApplicationBankService.findAll(
                {
                    paginate: 'none',
                    expand: 'plaid_link',
                },
                function (links) {
                    angular.forEach(links, function (link) {
                        if (link.last_retrieved_data_at) {
                            link.last_retrieved_data_at = moment.unix(link.last_retrieved_data_at).fromNow();
                        } else {
                            link.last_retrieved_data_at = 'Never';
                        }
                    });
                    $scope.bankAccounts = links;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function openPlaid(linkToken, bankAccount) {
            let handler = $window.Plaid.create({
                token: linkToken,
                onSuccess: function (public_token, metadata) {
                    if (bankAccount) {
                        $scope.$apply(function () {
                            bankAccount.needs_update = false;
                            $scope.upgradeLoading = false;
                        });
                    } else {
                        $scope.openingPlaid = false;
                        startDateModal({
                            token: public_token,
                            metadata: metadata,
                        });
                    }
                },
                onExit: function (err) {
                    // The user exited the Link flow.
                    $scope.$apply(function () {
                        $scope.upgradeLoading = false;
                        $scope.openingPlaid = false;
                    });

                    if (err != null) {
                        // The user encountered a Plaid API error prior to exiting.
                        let displayMessage = parsePlaidError(err);
                        if (!displayMessage) {
                            displayMessage =
                                'We had trouble communicating with Plaid. Please try again or contact Invoiced Support.';
                        }

                        Core.showMessage(displayMessage, 'error');
                    }
                },
            });

            handler.open();
        }

        function startDateModal(plaidData) {
            const modalInstance = $modal.open({
                templateUrl: 'settings/views/plaid-start-date.html',
                controller: 'PlaidStartDateController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(function (start_date) {
                plaidData.start_date = start_date;

                CashApplicationBankService.link(
                    {
                        expand: 'plaid_link',
                    },
                    plaidData,
                    function (links) {
                        // success
                        if (links[links.length - 1].message) {
                            Core.showMessage(links[links.length - 1].message, 'error');
                            delete links[links.length - 1];
                        }
                        angular.forEach(links, function (link) {
                            link.last_retrieved_data_at = 'Never';
                            $scope.bankAccounts.push(link);
                        });
                    },
                    function (result) {
                        // error
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
        }

        function parsePlaidError(error) {
            if (error.display_message) {
                return error.display_message;
            }

            // check for unsupported country
            if (-1 < error.error_message.indexOf('country')) {
                return 'Your country is not supported for bank feeds.';
            }

            return null;
        }

        function deleteMessage(link) {
            let escapeHtml = $filter('escapeHtml');
            return (
                '<p>Are you sure you want to delete this account?</p>' +
                '<p><strong>' +
                (link.institution_name ? escapeHtml(link.institution_name) + ' - ' : '') +
                (link.account_name ? escapeHtml(link.account_name) : '') +
                (link.account_last4 ? ' xxx' + escapeHtml(link.account_last4) : '') +
                '</strong></p>'
            );
        }

        function deleteLink(account, index) {
            CashApplicationBankService.delete(
                { id: account.id },
                function () {
                    $scope.bankAccounts.splice(index, 1);
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
