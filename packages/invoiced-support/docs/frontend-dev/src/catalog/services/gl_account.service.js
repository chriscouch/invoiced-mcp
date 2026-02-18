(function () {
    'use strict';

    angular.module('app.catalog').factory('GlAccount', GlAccountService);

    GlAccountService.$inject = ['$resource', '$http', '$cacheFactory', 'InvoicedConfig', 'selectedCompany'];

    function GlAccountService($resource, $http, $cacheFactory, InvoicedConfig, selectedCompany) {
        let accountList;
        let glAccountsCache = {};

        let GlAccount = $resource(
            InvoicedConfig.apiBaseUrl + '/gl_accounts/:id',
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
                    url: InvoicedConfig.apiBaseUrl + '/gl_accounts',
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

        GlAccount.all = function (success, error) {
            if (typeof glAccountsCache[selectedCompany.id] !== 'undefined') {
                success(glAccountsCache[selectedCompany.id]);
                return;
            }

            glAccountsCache[selectedCompany.id] = [];
            loadPage(1, success, error);
        };

        GlAccount.clearCache = clearCache;

        return GlAccount;

        function loadPage(page, success, error) {
            GlAccount.findAll(
                {
                    page: page,
                },
                function (glAccounts, headers) {
                    accountList = [];

                    let parents = {};
                    angular.forEach(glAccounts, function (glAccount) {
                        // determine the level of the account
                        // for rendering purposes
                        let level = 0;
                        if (typeof parents[glAccount.parent_id] !== 'undefined') {
                            level += parents[glAccount.parent_id] + 1;
                        }

                        parents[glAccount.id] = level;
                        glAccount.level = level;

                        accountList.push(glAccount);
                    });

                    glAccountsCache[selectedCompany.id] = glAccountsCache[selectedCompany.id].concat(accountList);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > glAccountsCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, success, error);
                    } else {
                        success(glAccountsCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof glAccountsCache[selectedCompany.id] !== 'undefined') {
                delete glAccountsCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
