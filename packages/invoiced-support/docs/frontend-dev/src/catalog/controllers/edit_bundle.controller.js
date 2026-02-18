(function () {
    'use strict';

    angular.module('app.catalog').controller('EditBundleController', EditBundleController);

    EditBundleController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'Bundle',
        'Settings',
        'IdGenerator',
        'Money',
        'model',
        'Feature',
    ];

    function EditBundleController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        Bundle,
        Settings,
        IdGenerator,
        Money,
        model,
        Feature,
    ) {
        if (model) {
            $scope.bundle = angular.copy(model);
        } else {
            $scope.bundle = {
                name: '',
                id: '',
                currency: selectedCompany.currency,
                items: [],
            };
        }

        $scope.company = selectedCompany;
        $scope.shouldGenID = !model.id;
        $scope.isExisting = !!model.id;
        $scope.hasMultiCurrency = Feature.hasFeature('multi_currency');

        $scope.sortableOptions = {
            handle: '.sortable-handle',
            placholder: 'sortable-placeholder',
        };

        loadSettings();

        $scope.generateID = function (model) {
            if (!$scope.shouldGenID && model.id) {
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

        $scope.addItem = function () {
            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/add-item.html',
                controller: 'AddItemController',
                resolve: {
                    currency: function () {
                        return $scope.bundle.currency;
                    },
                    requireCurrency: function () {
                        return true;
                    },
                    multiple: function () {
                        return true;
                    },
                },
                backdrop: 'static',
                keyboard: false,
                windowClass: 'add-item-modal',
            });

            modalInstance.result.then(
                function (items) {
                    angular.forEach(items, function (item) {
                        $scope.bundle.items.push({
                            catalog_item: item,
                            quantity: 1,
                        });
                    });
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.deleteItem = function (item) {
            let i = 0;
            for (i in $scope.bundle.items) {
                if ($scope.bundle.items[i].catalog_item == item.catalog_item) {
                    break;
                }
            }

            $scope.bundle.items.splice(i, 1);
        };

        $scope.save = function (bundle) {
            $scope.saving = true;
            $scope.error = null;

            bundle = angular.copy(bundle);

            // parse items
            angular.forEach(bundle.items, function (item) {
                item.catalog_item = item.catalog_item.id;
            });

            if ($scope.isExisting) {
                saveExisting(bundle);
            } else {
                saveNew(bundle);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.generateID($scope.bundle);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function saveExisting(bundle) {
            Bundle.edit(
                {
                    id: bundle.id,
                },
                {
                    name: bundle.name,
                    type: bundle.type,
                    items: bundle.items,
                },
                function (_bundle) {
                    $scope.saving = false;
                    $modalInstance.close(_bundle);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(bundle) {
            Bundle.create(
                {},
                bundle,
                function (_bundle) {
                    $scope.saving = false;

                    $modalInstance.close(_bundle);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.settings = settings;
            });
        }
    }
})();
