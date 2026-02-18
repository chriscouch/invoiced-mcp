(function () {
    'use strict';

    angular.module('app.catalog').factory('TaxRule', TaxRuleService);

    TaxRuleService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function TaxRuleService($resource, $http, InvoicedConfig, selectedCompany) {
        let taxRulesCache = {};

        let TaxRule = $resource(
            InvoicedConfig.apiBaseUrl + '/tax_rules/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/tax_rules',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );

        TaxRule.all = function (success, error) {
            if (typeof taxRulesCache[selectedCompany.id] !== 'undefined') {
                success(taxRulesCache[selectedCompany.id]);
                return;
            }

            taxRulesCache[selectedCompany.id] = [];
            TaxRule.findAll(
                { paginate: 'none' },
                function (result) {
                    taxRulesCache[selectedCompany.id] = result;
                    success(result);
                },
                error,
            );
        };

        TaxRule.clearCache = clearCache;

        return TaxRule;

        function clearCache(response) {
            if (typeof taxRulesCache[selectedCompany.id] !== 'undefined') {
                delete taxRulesCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
