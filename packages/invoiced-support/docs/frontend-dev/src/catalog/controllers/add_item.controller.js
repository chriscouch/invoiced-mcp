(function () {
    'use strict';

    angular.module('app.catalog').controller('AddItemController', AddItemController);

    AddItemController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$filter',
        '$timeout',
        'Item',
        'Core',
        'Settings',
        'selectedCompany',
        'currency',
        'requireCurrency',
        'multiple',
    ];

    function AddItemController(
        $scope,
        $modalInstance,
        $modal,
        $filter,
        $timeout,
        Item,
        Core,
        Settings,
        selectedCompany,
        currency,
        requireCurrency,
        multiple,
    ) {
        $scope.catalogItems = [];
        $scope.catalogItemsMap = {};
        $scope.selected = [];
        $scope.selectedMap = {};
        $scope.company = selectedCompany;
        $scope.multiple = multiple;

        $scope.newItemModal = function (name) {
            $('.add-item-modal').hide();

            name = name || '';

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-item.html',
                controller: 'EditItemController',
                backdrop: false,
                keyboard: false,
                resolve: {
                    model: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (newItem) {
                    $scope.catalogItems.push(newItem);
                    $scope.catalogItemsMap[newItem.id] = newItem;
                    $scope.toggleItem(newItem);
                    $('.add-item-modal').show();
                },
                function () {
                    // canceled
                    $('.add-item-modal').show();
                },
            );
        };

        $scope.toggleItem = function (item) {
            // select a single item if multiple selection is disabled
            if (!$scope.multiple) {
                $modalInstance.close([item]);
                return;
            }

            // add to the selected list
            if (typeof $scope.selectedMap[item.id] == 'undefined' || !$scope.selectedMap[item.id]) {
                $scope.selected.push(item.id);
                $scope.selectedMap[item.id] = true;
                // remove from the selected list
            } else {
                $scope.selected.splice($scope.selected.indexOf(item.id), 1);
                $scope.selectedMap[item.id] = false;
            }
        };

        $scope.isSelected = function (item) {
            return $scope.selectedMap[item.id];
        };

        $scope.addItems = function (selected) {
            let items = [];
            angular.forEach(selected, function (id) {
                items.push($scope.catalogItemsMap[id]);
            });

            $modalInstance.close(items);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        loadItems();
        loadSettings();

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function loadItems() {
            $scope.loading = true;

            Item.all(
                function (catalogItems) {
                    // restrict the items available to the given currency
                    let items = [];
                    let itemsMap = {};
                    angular.forEach(catalogItems, function (item) {
                        if (item.currency == currency || (!requireCurrency && !item.currency)) {
                            items.push(item);
                            itemsMap[item.id] = item;
                        }
                    });

                    $scope.catalogItems = items;
                    $scope.catalogItemsMap = itemsMap;

                    $scope.loading = false;

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
        }

        function loadSettings() {
            Settings.accountsReceivable(function (settings) {
                $scope.settings = settings;
            });
        }
    }
})();
