(function () {
    'use strict';

    angular.module('app.catalog').controller('AddRateController', AddRateController);

    AddRateController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$timeout',
        '$filter',
        'Coupon',
        'TaxRate',
        'Core',
        'selectedCompany',
        'currency',
        'ignore',
        'type',
        'options',
    ];

    function AddRateController(
        $scope,
        $modalInstance,
        $modal,
        $timeout,
        $filter,
        Coupon,
        TaxRate,
        Core,
        selectedCompany,
        currency,
        ignore,
        type,
        options,
    ) {
        $scope.rates = {};
        $scope.availableRates = [];
        $scope.company = selectedCompany;

        $scope.currency = currency;
        $scope.options = options || {};

        // convert applied rate type to rate type
        if (type == 'discount') {
            type = 'coupon';
        } else if (type == 'tax') {
            type = 'tax_rate';
        }

        // determine title
        let object;
        if (type == 'coupon') {
            $scope.title = 'Coupon';
            $scope.title_plural = 'Coupons';
            $scope.appliedRateTitle = 'Discount';
            object = Coupon;
        } else if (type == 'tax_rate') {
            $scope.title = 'Tax Rate';
            $scope.title_plural = 'Tax Rates';
            $scope.appliedRateTitle = 'Tax';
            object = TaxRate;
        }

        // build list of rates to ignore
        $scope.ignoreRates = [];
        angular.forEach(ignore, function (rate) {
            $scope.ignoreRates.push(rate.id);
        });

        $scope.loadRates = function () {
            $scope.loading = true;

            object.all(
                function (loadedRates) {
                    $scope.loading = false;

                    // build a master list of rates
                    $scope.rates = {};
                    $scope.hasRates = loadedRates.length > 0;
                    angular.forEach(loadedRates, function (rate) {
                        $scope.rates[rate.id] = rate;
                    });

                    // build an array of rates that excludes
                    // rates we want to ignore
                    $scope.availableRates = [];
                    angular.forEach($scope.rates, function (rate) {
                        if (
                            $scope.ignoreRates.indexOf(rate.id) === -1 &&
                            (!rate.currency || rate.currency == currency)
                        ) {
                            $scope.availableRates.push(rate);
                        }
                    });

                    // focus searchbar input (after timeout so DOM can render)
                    $timeout(function () {
                        $('.modal-selector .search input').focus();
                    }, 50);
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.newRateModal = function (name) {
            $('.add-rate-modal').hide();

            name = name || '';

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-rate.html',
                controller: 'EditRateController',
                resolve: {
                    model: function () {
                        let params = {
                            name: name,
                            is_percent: true,
                            currency: currency,
                            value: '',
                            metadata: {},
                        };

                        if (type === 'coupon') {
                            params.duration = 0;
                        } else if (type === 'tax_rate') {
                            params.inclusive = false;
                        }

                        return params;
                    },
                    type: function () {
                        return type;
                    },
                },
                backdrop: false,
                keyboard: false,
            });

            modalInstance.result.then(
                function (newRate) {
                    // add rate to our local lists
                    $scope.availableRates.push(newRate);
                    $scope.rates[newRate.id] = newRate;

                    $('.add-rate-modal').show();

                    // select the new rate
                    $scope.select(newRate);
                },
                function () {
                    // canceled
                    $('.add-rate-modal').show();
                },
            );
        };

        $scope.select = function (rate) {
            $modalInstance.close(rate);
        };

        $scope.customAmount = function () {
            $modalInstance.close(0);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.loadRates();

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
