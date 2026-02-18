(function () {
    'use strict';

    angular.module('app.network').controller('AssignVendorToNetworkController', AssignVendorToNetworkController);

    AssignVendorToNetworkController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'Network',
        'Vendor',
        'Core',
        'vendor',
    ];

    function AssignVendorToNetworkController($scope, $modalInstance, $modal, Network, Vendor, Core, vendor) {
        $scope.vendor = vendor;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.save = function (connection) {
            $scope.saving = true;
            Vendor.edit(
                {
                    id: vendor.id,
                },
                {
                    network_connection: connection.id,
                },
                function () {
                    $scope.saving = false;
                    vendor.network_connection = connection.id;
                    $modalInstance.close(connection);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        load();

        function load() {
            $scope.loading = true;

            Network.allVendors(
                function (connections) {
                    $scope.loading = false;
                    $scope.connections = connections;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
