/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('ItemsSettingsController', ItemsSettingsController);

    ItemsSettingsController.$inject = [
        '$scope',
        '$modal',
        'LeavePageWarning',
        'Item',
        'Settings',
        'selectedCompany',
        'Core',
    ];

    function ItemsSettingsController($scope, $modal, LeavePageWarning, Item, Settings, selectedCompany, Core) {
        $scope.company = angular.copy(selectedCompany);

        $scope.items = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.editItemModal = function (item) {
            LeavePageWarning.block();

            item = item || false;

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-item.html',
                controller: 'EditItemController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        if (!item) {
                            return false;
                        }

                        return item;
                    },
                },
            });

            modalInstance.result.then(
                function (_item) {
                    LeavePageWarning.unblock();

                    if (item) {
                        angular.extend(item, _item);
                    } else {
                        $scope.items.push(_item);
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (item) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this item?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[item.id] = true;
                        $scope.error = null;

                        Item.delete(
                            {
                                id: item.id,
                            },
                            function () {
                                $scope.deleting[item.id] = false;

                                Core.flashMessage('The item, ' + item.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.items) {
                                    if ($scope.items[i].id == item.id) {
                                        $scope.items.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                Item.clearCache();
                            },
                            function (result) {
                                $scope.deleting[item.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Items');

        loadItems();
        loadSettings();

        function loadItems() {
            $scope.loading = true;

            Item.all(
                function (catalogItems) {
                    $scope.loading = false;
                    $scope.items = catalogItems;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadSettings() {
            Settings.accountsReceivable(
                function (settings) {
                    $scope.settings = settings;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
