(function () {
    'use strict';

    angular.module('app.catalog').controller('AddBundleController', AddBundleController);

    AddBundleController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$filter',
        '$timeout',
        'Bundle',
        'Core',
        'selectedCompany',
        'currency',
    ];

    function AddBundleController(
        $scope,
        $modalInstance,
        $modal,
        $filter,
        $timeout,
        Bundle,
        Core,
        selectedCompany,
        currency,
    ) {
        $scope.bundles = [];
        $scope.company = selectedCompany;

        $scope.newBundleModal = function (name) {
            $('.add-bundle-modal').hide();

            name = name || '';

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-bundle.html',
                controller: 'EditBundleController',
                backdrop: false,
                keyboard: false,
                resolve: {
                    model: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function (newBundle) {
                    $scope.bundles.push(newBundle);
                    $scope.select(newBundle);
                    $('.add-bundle-modal').show();
                },
                function () {
                    // canceled
                    $('.add-bundle-modal').show();
                },
            );
        };

        $scope.select = function (bundle) {
            $modalInstance.close(bundle);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        loadBundles();

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function loadBundles() {
            $scope.loading = true;

            Bundle.all(
                function (_bundles) {
                    // restrict the bundles available to the given currency
                    let bundles = [];
                    angular.forEach(_bundles, function (bundle) {
                        if (bundle.currency == currency) {
                            bundles.push(bundle);
                        }
                    });

                    $scope.bundles = bundles;

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
    }
})();
