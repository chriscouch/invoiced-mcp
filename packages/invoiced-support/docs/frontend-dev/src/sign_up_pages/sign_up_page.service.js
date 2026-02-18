(function () {
    'use strict';

    angular.module('app.sign_up_pages').factory('SignUpPage', SignUpPageService);

    SignUpPageService.$inject = ['$resource', 'InvoicedConfig', 'selectedCompany'];

    function SignUpPageService($resource, InvoicedConfig, selectedCompany) {
        let signUpPageCache = {};

        let SignUpPage = $resource(
            InvoicedConfig.apiBaseUrl + '/sign_up_pages/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        per_page: 100,
                    },
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/sign_up_pages',
                },
                edit: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );

        SignUpPage.all = function (success, error) {
            if (typeof signUpPageCache[selectedCompany.id] !== 'undefined') {
                success(signUpPageCache[selectedCompany.id]);
                return;
            }

            signUpPageCache[selectedCompany.id] = [];
            loadPage(1, success, error);
        };

        SignUpPage.clearCache = clearCache;

        return SignUpPage;

        function loadPage(page, success, error) {
            SignUpPage.findAll(
                {
                    page: page,
                },
                function (pages, headers) {
                    signUpPageCache[selectedCompany.id] = signUpPageCache[selectedCompany.id].concat(pages);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > signUpPageCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, success, error);
                    } else {
                        success(signUpPageCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof signUpPageCache[selectedCompany.id] !== 'undefined') {
                delete signUpPageCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
