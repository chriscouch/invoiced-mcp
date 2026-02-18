(function () {
    'use strict';

    angular.module('app.billing').directive('invoicedCoupon', invoicedCoupon);

    function invoicedCoupon() {
        return {
            restrict: 'E',
            templateUrl: 'billing/views/invoiced-coupon.html',
            scope: {
                coupon: '=',
            },
        };
    }
})();
