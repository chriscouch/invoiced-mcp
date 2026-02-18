/* globals _, google */
(function () {
    'use strict';

    angular.module('app.components').directive('addressValidator', addressValidator);

    function addressValidator() {
        return {
            restrict: 'E',
            template:
                '<a href="" tooltip="Address appears valid on Google Maps!" ng-show="status==\'found\'">' +
                '<span class="fas fa-check text-success"></span>' +
                '</a>' +
                '<a href="" tooltip="Address seems partially correct, exact match not found on Google Maps" ng-show="status==\'partial\'">' +
                '<span class="fas fa-question text-warning"></span>' +
                '</a>' +
                '<a href="" tooltip="No match found for address on Google Maps" ng-show="status==\'no_match\'">' +
                '<span class="fas fa-times text-danger"></span>' +
                '</a>',
            scope: {
                object: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.$watch('object', _.debounce(validate, 300), true);

                    let addressKeys = ['address1', 'city', 'state', 'postal_code', 'country'];

                    let geocoder;
                    if (typeof google !== 'undefined') {
                        geocoder = new google.maps.Geocoder();
                    }

                    let lastAddress;

                    function validate(object) {
                        if (typeof object !== 'object') {
                            return;
                        }

                        let address = [];
                        for (let i in addressKeys) {
                            let k = addressKeys[i];

                            // cannot geocode with missing components
                            if (!object[k]) {
                                $scope.status = null;
                                return;
                            }

                            address.push(object[k]);
                        }

                        address = address.join(', ');

                        if (!geocoder || address === lastAddress) {
                            return;
                        }

                        $scope.status = null;
                        geocoder.geocode(
                            {
                                address: address,
                            },
                            function (results, status) {
                                lastAddress = address;

                                if (status == google.maps.GeocoderStatus.OK) {
                                    let meta = results[0];

                                    let isMatch =
                                        meta.types.indexOf('street_address') !== -1 ||
                                        meta.types.indexOf('premise') !== -1 ||
                                        meta.types.indexOf('subpremise') !== -1;

                                    // found
                                    if (isMatch && !meta.partial_match) {
                                        $scope.$apply(function () {
                                            $scope.status = 'found';
                                        });
                                        // partial match means part of the address was valid
                                        // however something was off, like the house #
                                    } else if (meta.partial_match) {
                                        $scope.$apply(function () {
                                            $scope.status = 'partial';
                                        });
                                        // not found
                                    } else {
                                        $scope.$apply(function () {
                                            $scope.status = 'no_match';
                                        });
                                    }
                                    // geocoder error
                                } else {
                                    $scope.$apply(function () {
                                        $scope.status = 'no_match';
                                    });
                                }
                            },
                        );
                    }
                },
            ],
        };
    }
})();
