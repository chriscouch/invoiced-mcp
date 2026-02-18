(function () {
    'use strict';

    angular.module('app.components').directive('cardIcon', cardIcon);

    function cardIcon() {
        return {
            restrict: 'E',
            template: '<div class="card-icon"><img ng-src="{{url}}" /></div>',
            scope: {
                brand: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    let brands = [
                        '2checkout',
                        'american-express',
                        'cirrus',
                        'delta',
                        'direct-debit',
                        'discover',
                        'ebay',
                        'google-checkout',
                        'maestro',
                        'mastercard',
                        'moneybookers',
                        'paypal',
                        'sagepay',
                        'solo',
                        'visa',
                        'visa-electron',
                        'western-union',
                    ];

                    $scope.url = buildUrl($scope.brand);

                    $scope.$watch('brand', function (brand) {
                        $scope.url = buildUrl(brand, 'curved', 64);
                    });

                    function buildUrl(brand, edge, size) {
                        brand = (brand || '').toLowerCase().replace(' ', '-');
                        if (brand === 'amex') {
                            brand = 'american-express';
                        }

                        if (brands.indexOf(brand) < 0) {
                            return '/img/icons/credit_card.png';
                        }

                        return '/img/payment-icons/' + brand + '-' + edge + '-' + size + 'px.png';
                    }
                },
            ],
        };
    }
})();
