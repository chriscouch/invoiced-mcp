(function () {
    'use strict';

    angular.module('app.catalog').directive('coupons', coupons);

    function coupons() {
        return {
            restrict: 'E',
            templateUrl: 'catalog/views/coupons.html',
            scope: {
                coupons: '=',
                currency: '=',
            },
            controller: [
                '$scope',
                'selectedCompany',
                'Money',
                function ($scope, selectedCompany, Money) {
                    $scope.value = function (coupon) {
                        if (typeof coupon === 'undefined') {
                            return '';
                        }

                        if (coupon.is_percent) {
                            return coupon.value + '%';
                        } else {
                            return Money.currencyFormat(coupon.value, coupon.currency, selectedCompany.moneyFormat);
                        }
                    };
                },
            ],
        };
    }
})();
