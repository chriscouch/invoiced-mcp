(function () {
    'use strict';

    angular.module('app.network').controller('InviteToNetworkController', InviteToNetworkController);

    InviteToNetworkController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        'selectedCompany',
        'Network',
        'customer',
        'vendor',
    ];

    function InviteToNetworkController($scope, $modalInstance, $modal, selectedCompany, Network, customer, vendor) {
        $scope.customer = customer;
        $scope.vendor = vendor;
        $scope.to = customer ? customer.email : '';

        $scope.invite = function (to) {
            $scope.saving = true;
            $scope.error = null;

            Network.sendInvite(
                {
                    to: to,
                    customer: customer ? customer.id : null,
                    vendor: vendor ? vendor.id : null,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.assignExistingCustomer = function (customer) {
            const modalInstance = $modal.open({
                templateUrl: 'network/views/assign-customer.html',
                controller: 'AssignCustomerToNetworkController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return customer;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    $modalInstance.close();
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.assignExistingVendor = function (vendor) {
            const modalInstance = $modal.open({
                templateUrl: 'network/views/assign-vendor.html',
                controller: 'AssignVendorToNetworkController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    vendor: function () {
                        return vendor;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    $modalInstance.close();
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
