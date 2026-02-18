/* globals Invoiced */
(function () {
    'use strict';

    angular.module('app.payment_setup').factory('PaymentTokens', PaymentTokens);

    function PaymentTokens() {
        return {
            tokenizeCard: tokenizeCard,
            tokenizeBankAccount: tokenizeBankAccount,
        };

        function tokenizeCard(card, gateway, success, error) {
            let tokenizeHandlerInvoiced = function (statusCode, response) {
                if (statusCode >= 400) {
                    error(response.message);
                } else {
                    success(response);
                }
            };

            // convert card to Invoiced address format
            card.address = {
                address1: card.address_line1,
                address2: card.address_line2,
                city: card.address_city,
                state: card.address_state,
                postal_code: card.address_zip,
                country: card.address_country,
            };
            delete card.address_line1;
            delete card.address_line2;
            delete card.address_city;
            delete card.address_state;
            delete card.address_zip;
            delete card.address_country;

            // tokenize on Invoiced payment system
            Invoiced.card.tokenize(
                card.number,
                card.cvc,
                card.exp_month,
                card.exp_year,
                card.name,
                card.address,
                tokenizeHandlerInvoiced,
            );
        }

        function tokenizeBankAccount(bankAccount, gateway, success, error) {
            let tokenizeHandlerInvoiced = function (statusCode, response) {
                if (statusCode >= 400) {
                    error(response.message);
                } else {
                    success(response);
                }
            };

            Invoiced.bankAccount.tokenize(
                bankAccount.account_holder_name,
                bankAccount.account_holder_type,
                bankAccount.account_number,
                bankAccount.routing_number,
                tokenizeHandlerInvoiced,
            );
        }
    }
})();
