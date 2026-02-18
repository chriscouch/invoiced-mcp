(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('EditVendorController', EditVendorController);

    EditVendorController.$inject = ['$scope', '$modalInstance', 'Vendor', '$translate', 'selectedCompany', 'model'];

    function EditVendorController($scope, $modalInstance, Vendor, $translate, selectedCompany, model) {
        if (model) {
            $scope.vendor = angular.copy(model);
        } else {
            $scope.vendor = {
                active: true,
                country: selectedCompany.country,
            };
        }

        $scope.save = saveVendor;

        $scope.changeCountry = function (country) {
            let locale = 'en_' + country;
            $scope.cityLabel = $translate.instant('address.city', {}, null, locale);
            $scope.stateLabel = $translate.instant('address.state', {}, null, locale);
            $scope.postalCodeLabel = $translate.instant('address.postal_code', {}, null, locale);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.changeCountry($scope.vendor.country);

        function saveVendor(vendor) {
            $scope.saving = true;
            $scope.error = null;

            let params = {
                name: vendor.name,
                number: vendor.number,
                email: vendor.email,
                active: vendor.active,
                approval_workflow: vendor.approval_workflow ? vendor.approval_workflow.id : null,
                address1: vendor.address1,
                address2: vendor.address2,
                city: vendor.city,
                state: vendor.state,
                postal_code: vendor.postal_code,
                country: vendor.country,
            };

            if (vendor.id) {
                Vendor.edit(
                    {
                        id: vendor.id,
                    },
                    params,
                    function (_vendor) {
                        $scope.saving = false;
                        if (vendor.approval_workflow) {
                            _vendor.approval_workflow = vendor.approval_workflow;
                        }
                        $modalInstance.close(_vendor);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            } else {
                Vendor.create(
                    params,
                    function (_vendor) {
                        $scope.saving = false;
                        if (vendor.approval_workflow) {
                            _vendor.approval_workflow = vendor.approval_workflow;
                        }
                        $modalInstance.close(_vendor);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            }
        }
    }
})();
