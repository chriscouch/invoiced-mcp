(function () {
    'use strict';

    angular.module('app.accounts_receivable').filter('rateList', rateList);

    rateList.$inject = ['$filter', 'selectedCompany', 'Money'];

    function rateList($filter, selectedCompany, Money) {
        return function (model, currency) {
            let appliedRateName = $filter('appliedRateName');
            let rateSummaries = [];
            let rateObjectKeys = {
                discounts: 'coupon',
                taxes: 'tax_rate',
                shipping: 'shipping_rate',
            };

            angular.forEach(['discounts', 'taxes', 'shipping'], function (type) {
                angular.forEach(model[type], function (appliedRate) {
                    // convert Rates into Applied Rates
                    let k = rateObjectKeys[type];
                    if (typeof appliedRate[k] === 'undefined') {
                        appliedRate = {
                            k: appliedRate,
                        };
                    }

                    let summary = appliedRateName(appliedRate, type) + ': ';
                    summary += Money.currencyFormat(appliedRate.amount, currency, selectedCompany.moneyFormat);

                    rateSummaries.push(summary);
                });
            });

            return rateSummaries.join(', ');
        };
    }
})();
