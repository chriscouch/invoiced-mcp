/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('BundlesSettingsController', BundlesSettingsController);

    BundlesSettingsController.$inject = [
        '$scope',
        '$modal',
        'Company',
        'Bundle',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
    ];

    function BundlesSettingsController($scope, $modal, Company, Bundle, LeavePageWarning, selectedCompany, Core) {
        $scope.company = angular.copy(selectedCompany);

        $scope.bundles = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.editBundleModal = function (bundle) {
            LeavePageWarning.block();

            bundle = bundle || false;

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-bundle.html',
                controller: 'EditBundleController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        if (!bundle) {
                            return false;
                        }

                        return bundle;
                    },
                },
            });

            modalInstance.result.then(
                function (_bundle) {
                    LeavePageWarning.unblock();

                    if (bundle) {
                        angular.extend(bundle, _bundle);
                    } else {
                        $scope.bundles.push(_bundle);
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (bundle) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this bundle?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[bundle.id] = true;
                        $scope.error = null;

                        Bundle.delete(
                            {
                                id: bundle.id,
                            },
                            function () {
                                $scope.deleting[bundle.id] = false;

                                Core.flashMessage('The bundle, ' + bundle.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.bundles) {
                                    if ($scope.bundles[i].id == bundle.id) {
                                        $scope.bundles.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                Bundle.clearCache();
                            },
                            function (result) {
                                $scope.deleting[bundle.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Bundles');

        loadBundles();

        function loadBundles() {
            $scope.loading = true;

            Bundle.all(
                function (bundles) {
                    $scope.bundles = bundles;
                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
