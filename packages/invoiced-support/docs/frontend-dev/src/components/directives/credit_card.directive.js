(function () {
    'use strict';

    angular.module('app.components').directive('creditCard', creditCard);

    function creditCard() {
        return {
            restrict: 'E',
            templateUrl: 'components/views/credit-card.html',
            scope: {
                card: '=',
                options: '=?',
            },
            link: function (scope, element) {
                let $element = $(element);

                $('.cc-num', $element).payment('formatCardNumber');
                $('.cc-exp', $element).payment('formatCardExpiry');
                $('.cc-cvc', $element).payment('formatCardCVC');
            },
            controller: [
                '$scope',
                '$translate',
                'selectedCompany',
                'InvoicedConfig',
                function ($scope, $translate, selectedCompany, InvoicedConfig) {
                    $scope.options = $scope.options || {};
                    let options = {
                        requestAddress: true,
                        allowTestCard: true,
                    };
                    angular.extend(options, $scope.options);
                    $scope.options = options;
                    changeCountry($scope.card.address_country);

                    $scope.cardType = null;

                    $scope.testMode =
                        options.allowTestCard && (selectedCompany.test_mode || InvoicedConfig.environment === 'dev');

                    $scope.testCard = function () {
                        $scope.card.number = '4242424242424242';
                        $scope.card.expiry = '05/26';
                        $scope.card.cvc = '123';
                        $scope.card.name = 'Test User';
                        $scope.card.address_line1 = '1100 Congress Ave';
                        $scope.card.address_line2 = null;
                        $scope.card.address_city = 'Austin';
                        $scope.card.address_state = 'TX';
                        $scope.card.address_zip = '78701';
                        $scope.card.address_country = 'US';

                        $scope.changeCardNumber();
                        $scope.changeExpiry();
                        changeCountry('US');
                    };

                    $scope.changeCardNumber = function () {
                        $scope.cardType = $.payment.cardType($scope.card.number);
                    };

                    $scope.changeExpiry = function () {
                        // build card object
                        let expiry = ($scope.card.expiry || '').split('/');

                        $scope.card.exp_month = $.trim(expiry[0] || '');
                        $scope.card.exp_year = $.trim(expiry[1] || '');
                    };

                    $scope.changeCountry = changeCountry;

                    function changeCountry(country) {
                        if (typeof country !== 'string') {
                            country = selectedCompany.country;
                        }

                        let locale = 'en_' + country;
                        $scope.cityLabel = $translate.instant('address.city', {}, null, locale);
                        $scope.stateLabel = $translate.instant('address.state', {}, null, locale);
                        $scope.postalCodeLabel = $translate.instant('address.postal_code', {}, null, locale);
                    }
                },
            ],
        };
    }
})();
