/* globals inflection */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('PaymentDisplayHelper', PaymentDisplayHelper);

    function PaymentDisplayHelper() {
        return {
            format: format,
            formatBankAccount: formatBankAccount,
            formatBankName: formatBankName,
            formatCard: formatCard,
            formatCardBrand: formatCardBrand,
        };

        function format(source) {
            if (!source) {
                return '';
            }

            if (source.object === 'bank_account') {
                return formatBankAccount(source.bank_name, source.last4);
            }

            if (source.object === 'card') {
                return formatCard(source.brand, source.last4);
            }

            return '';
        }

        function formatBankAccount(bankName, last4) {
            return (formatBankName(bankName) + ' *' + last4).trim();
        }

        function formatBankName(bankName) {
            bankName = bankName + '';
            switch (bankName.toLowerCase()) {
                case 'unknown':
                    return '';
                default:
                    // Titleize only if all uppercase
                    if (bankName.toUpperCase() === bankName) {
                        return inflection.titleize(bankName);
                    }

                    return bankName;
            }
        }

        function formatCard(brand, last4, expMonth, expYear) {
            let result = formatCardBrand(brand) + ' *' + last4;

            if (expMonth && expYear) {
                result += ' (expires ' + expMonth + ' / ' + expYear + ')';
            }

            return result.trim();
        }

        function formatCardBrand(brand) {
            brand = brand + '';
            switch (brand.toLowerCase()) {
                case 'amex':
                case 'americanexpress':
                case 'american express':
                    return 'Amex';
                case 'cartebancaire':
                    return 'Carte Bancaires';
                case 'cirrus':
                    return 'Cirrus';
                case 'codensa':
                    return 'Codensa';
                case 'cup':
                    return 'China Union Pay';
                case 'dankort':
                    return 'Dankort';
                case 'diners':
                case 'diners club':
                case 'diners club international':
                case 'dinersclub':
                    return 'Diners Club';
                case 'discover':
                    return 'Discover';
                case 'electron':
                    return 'Electron';
                case 'elo':
                    return 'ELO';
                case 'jcb':
                    return 'JCB';
                case 'laser':
                    return 'Laser';
                case 'maestro':
                    return 'Maestro';
                case 'maestrouk':
                    return 'Maestro UK';
                case 'mc':
                case 'mastercard':
                    return 'Mastercard';
                case 'solo':
                    return 'Solo';
                case 'unknown':
                    return '';
                case 'visa':
                    return 'Visa';
                default:
                    // Titleize only if all uppercase
                    if (brand.toUpperCase() === brand) {
                        return inflection.titleize(brand);
                    }

                    return brand;
            }
        }
    }
})();
