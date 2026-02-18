(function () {
    'use strict';

    angular.module('app.components').directive('countryIcon', countryIcon);

    function countryIcon() {
        return {
            restrict: 'E',
            template:
                '<a href="" tooltip="{{countryName}}" ng-show="countryName"><img ng-src="{{url}}" width="23" height="17" /></a>',
            scope: {
                country: '=',
            },
            controller: [
                '$scope',
                'Core',
                function ($scope, Core) {
                    $scope.$watch('country', function (id) {
                        id = (id + '').toUpperCase();
                        let country = Core.getCountryFromCode(id);

                        if (country) {
                            $scope.url = '/img/country-icons/' + id + '@2x.png';
                            $scope.countryName = country.country;
                        } else {
                            $scope.url = false;
                            $scope.countryName = false;
                        }
                    });
                },
            ],
        };
    }
})();
