(function () {
    'use strict';

    angular.module('app.network').controller('AssignCustomerToNetworkController', AssignCustomerToNetworkController);

    AssignCustomerToNetworkController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'Network',
        'Customer',
        'Core',
        'customer',
    ];

    function AssignCustomerToNetworkController($scope, $modalInstance, $modal, Network, Customer, Core, customer) {
        $scope.customer = customer;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.save = function (connection) {
            $scope.saving = true;
            Customer.edit(
                {
                    id: customer.id,
                },
                {
                    network_connection: connection.id,
                },
                function () {
                    $scope.saving = false;
                    customer.network_connection = connection.id;
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

            Network.allCustomers(
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
