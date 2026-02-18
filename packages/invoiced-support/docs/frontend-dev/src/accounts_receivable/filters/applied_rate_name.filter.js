(function () {
    'use strict';

    angular.module('app.accounts_receivable').filter('appliedRateName', appliedRateName);

    appliedRateName.$inject = ['Core', 'selectedCompany'];

    function appliedRateName(Core, selectedCompany) {
        let rateObjectKeys = {
            discounts: 'coupon',
            taxes: 'tax_rate',
            shipping: 'shipping_rate',
        };

        let defaultTitles = {
            discounts: 'Discount',
            shipping: 'Shipping',
        };

        return function (appliedRate, type) {
            if (typeof appliedRate === 'undefined') {
                return '';
            }

            // ensure type string is plural
            if (type === 'discount') {
                type = 'discounts';
            } else if (type === 'tax') {
                type = 'taxes';
            }

            // check if this is a Rate object instead
            // of an Applied Rate
            let k = rateObjectKeys[type];
            if (typeof appliedRate[k] === 'undefined') {
                let _appliedRate = {};
                _appliedRate[k] = appliedRate;
                appliedRate = _appliedRate;
            }

            if (appliedRate[k]) {
                return appliedRate[k].name;
            }

            if (type === 'taxes') {
                return Core.taxLabelForCountry(selectedCompany.country);
            }

            return defaultTitles[type];
        };
    }
})();
