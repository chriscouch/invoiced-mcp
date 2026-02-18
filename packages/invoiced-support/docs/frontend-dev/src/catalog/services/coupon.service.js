(function () {
    'use strict';

    angular.module('app.catalog').factory('Coupon', CouponService);

    CouponService.$inject = ['$resource', '$http', 'InvoicedConfig', 'selectedCompany'];

    function CouponService($resource, $http, InvoicedConfig, selectedCompany) {
        let couponsCache = {};

        let Coupon = $resource(
            InvoicedConfig.apiBaseUrl + '/coupons/:id',
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
                    url: InvoicedConfig.apiBaseUrl + '/coupons',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
                edit: {
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(clearCache),
                },
            },
        );

        Coupon.all = function (success, error) {
            if (typeof couponsCache[selectedCompany.id] !== 'undefined') {
                success(couponsCache[selectedCompany.id]);
                return;
            }

            couponsCache[selectedCompany.id] = [];
            loadPage(1, success, error);
        };

        Coupon.clearCache = clearCache;

        return Coupon;

        function loadPage(page, success, error) {
            Coupon.findAll(
                {
                    page: page,
                },
                function (coupons, headers) {
                    couponsCache[selectedCompany.id] = couponsCache[selectedCompany.id].concat(coupons);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > couponsCache[selectedCompany.id].length;
                    if (hasMore) {
                        loadPage(page + 1, success, error);
                    } else {
                        success(couponsCache[selectedCompany.id]);
                    }
                },
                error,
            );
        }

        function clearCache(response) {
            if (typeof couponsCache[selectedCompany.id] !== 'undefined') {
                delete couponsCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
