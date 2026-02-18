(function () {
    'use strict';

    angular.module('app.catalog').factory('Plan', PlanService);

    PlanService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function PlanService($resource, $http, InvoicedConfig, selectedCompany) {
        let plansCache = {};

        let Plan = $resource(
            InvoicedConfig.apiBaseUrl + '/plans/:id',
            {},
            {
                findAll: {
                    method: 'GET',
                    params: {
                        include: 'num_subscriptions',
                        per_page: 100,
                    },
                    isArray: true,
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/plans',
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

        Plan.all = function (success, error) {
            if (typeof plansCache[selectedCompany.id] !== 'undefined') {
                success(plansCache[selectedCompany.id]);
                return;
            }

            plansCache[selectedCompany.id] = [];
            loadPage(1, success, error);
        };

        Plan.clearCache = clearCache;

        return Plan;

        function loadPage(page, success, error) {
            Plan.findAll(
                {
                    page: page,
                },
                function (plans, headers) {
                    plansCache[selectedCompany.id] = plansCache[selectedCompany.id].concat(plans);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > plansCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, success, error);
                    } else {
                        success(plansCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof plansCache[selectedCompany.id] !== 'undefined') {
                delete plansCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
