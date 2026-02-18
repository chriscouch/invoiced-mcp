(function () {
    'use strict';

    angular.module('app.catalog').controller('EditRateController', EditRateController);

    EditRateController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'Coupon',
        'TaxRate',
        'IdGenerator',
        'model',
        'type',
        'Feature',
    ];

    function EditRateController(
        $scope,
        $modalInstance,
        selectedCompany,
        Coupon,
        TaxRate,
        IdGenerator,
        model,
        type,
        Feature,
    ) {
        $scope.model = angular.copy(model);
        $scope.currency = selectedCompany.currency;
        $scope.isExisting = !!model.id;
        $scope.shouldGenID = true;
        $scope.type = type;
        $scope.changePricing = false;
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');

        if (typeof model.duration !== 'undefined') {
            if (model.duration === 0) {
                $scope.couponDuration = 'forever';
            } else if (model.duration === 1) {
                $scope.couponDuration = 'one_time';
            } else {
                $scope.couponDuration = 'limited';
            }
        }

        // determine title
        let object;
        if (type == 'coupon') {
            $scope.title = 'Coupon';
            object = Coupon;
        } else if (type == 'tax_rate') {
            $scope.title = 'Tax Rate';
            object = TaxRate;
        }

        // set currency (if missing)
        if ($scope.isExisting && !$scope.model.currency) {
            $scope.model.currency = selectedCompany.currency;
        }

        $scope.title_lower = $scope.title.toLowerCase();

        $scope.generateID = function (model) {
            if (!$scope.shouldGenID || $scope.isExisting) {
                return;
            }

            $scope.shouldGenID = true;
            if (!model.name) {
                model.id = '';
                return;
            }

            // generate ID as the user types the name
            // i.e. Invoiced Pro -> invoiced-pro
            model.id = IdGenerator.generate(model.name);
        };

        $scope.save = function (rate) {
            $scope.saving = true;
            $scope.error = null;

            if (type === 'coupon') {
                if ($scope.couponDuration === 'forever') {
                    rate.duration = 0;
                } else if ($scope.couponDuration === 'one_time') {
                    rate.duration = 1;
                }
            }

            if ($scope.isExisting) {
                if ($scope.changePricing) {
                    deleteAndCreate(rate);
                } else {
                    saveExisting(rate);
                }
            } else {
                saveNew(rate);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.generateID($scope.model);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function saveExisting(rate) {
            object.edit(
                {
                    id: rate.id,
                },
                {
                    name: rate.name,
                },
                function (_rate) {
                    $scope.saving = false;
                    $modalInstance.close(_rate);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function deleteAndCreate(rate) {
            object.delete(
                {
                    id: rate.id,
                },
                function () {
                    let params = {
                        id: rate.id,
                        name: rate.name,
                        currency: rate.currency,
                        value: rate.value,
                        is_percent: rate.is_percent,
                        metadata: rate.metadata,
                    };

                    if (type === 'coupon') {
                        params.duration = rate.duration;
                    } else if (type === 'tax_rate') {
                        params.inclusive = rate.inclusive;
                    }

                    saveNew(params);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(rate) {
            object.create(
                {},
                rate,
                function (_rate) {
                    $scope.saving = false;
                    $modalInstance.close(_rate);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
