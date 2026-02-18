(function () {
    'use strict';

    angular.module('app.billing').directive('stripeCoupon', stripeCoupon);

    function stripeCoupon() {
        return {
            restrict: 'E',
            templateUrl: 'billing/views/stripe-coupon.html',
            scope: {
                coupon: '=',
            },
        };
    }
})();
