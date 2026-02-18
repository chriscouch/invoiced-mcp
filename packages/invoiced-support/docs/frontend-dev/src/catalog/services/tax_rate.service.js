(function () {
    'use strict';

    angular.module('app.catalog').factory('TaxRate', TaxRateService);

    TaxRateService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function TaxRateService($resource, $http, InvoicedConfig, selectedCompany) {
        let taxRatesCache = {};

        let TaxRate = $resource(
            InvoicedConfig.apiBaseUrl + '/tax_rates/:id',
            {},
            {
                findAll: {
                    method: 'GET',
                    params: {
                        per_page: 100,
                    },
                    isArray: true,
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/tax_rates',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
            },
        );

        TaxRate.all = function (success, error) {
            if (typeof taxRatesCache[selectedCompany.id] !== 'undefined') {
                success(taxRatesCache[selectedCompany.id]);
                return;
            }

            taxRatesCache[selectedCompany.id] = [];
            loadPage(1, success, error);
        };

        TaxRate.clearCache = clearCache;

        return TaxRate;

        function loadPage(page, success, error) {
            TaxRate.findAll(
                {
                    page: page,
                },
                function (taxRates, headers) {
                    taxRatesCache[selectedCompany.id] = taxRatesCache[selectedCompany.id].concat(taxRates);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > taxRatesCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, success, error);
                    } else {
                        success(taxRatesCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof taxRatesCache[selectedCompany.id] !== 'undefined') {
                delete taxRatesCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
