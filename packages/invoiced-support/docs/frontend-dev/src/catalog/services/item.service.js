(function () {
    'use strict';

    angular.module('app.catalog').factory('Item', ItemService);

    ItemService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function ItemService($resource, $http, InvoicedConfig, selectedCompany) {
        let itemsCache = {};

        let Item = $resource(
            InvoicedConfig.apiBaseUrl + '/items/:id',
            {},
            {
                findAll: {
                    method: 'GET',
                    params: {
                        sort: 'type ASC,name ASC,unit_cost ASC',
                        per_page: 100,
                    },
                    isArray: true,
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/items',
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

        Item.all = function (success, error) {
            if (typeof itemsCache[selectedCompany.id] !== 'undefined') {
                success(itemsCache[selectedCompany.id]);
                return;
            }

            itemsCache[selectedCompany.id] = [];
            loadPage(1, success, error);
        };

        Item.clearCache = clearCache;

        return Item;

        function loadPage(page, success, error) {
            Item.findAll(
                {
                    page: page,
                },
                function (catalogItems, headers) {
                    itemsCache[selectedCompany.id] = itemsCache[selectedCompany.id].concat(catalogItems);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > itemsCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, success, error);
                    } else {
                        success(itemsCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof itemsCache[selectedCompany.id] !== 'undefined') {
                delete itemsCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
