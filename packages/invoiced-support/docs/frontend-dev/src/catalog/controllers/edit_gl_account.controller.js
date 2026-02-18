(function () {
    'use strict';

    angular.module('app.catalog').controller('EditGlAccountController', EditGlAccountController);

    EditGlAccountController.$inject = [
        '$scope',
        '$modal',
        '$modalInstance',
        'selectedCompany',
        'GlAccount',
        'Settings',
        'IdGenerator',
        'Money',
        'model',
    ];

    function EditGlAccountController(
        $scope,
        $modal,
        $modalInstance,
        selectedCompany,
        GlAccount,
        Settings,
        IdGenerator,
        Money,
        model,
    ) {
        if (model) {
            $scope.glAccount = angular.copy(model);
            $scope.isSubaccount = !!model.parent_id;
        } else {
            $scope.glAccount = {
                name: '',
                code: '',
                parent_id: null,
            };
            $scope.isSubaccount = false;
        }

        $scope.company = selectedCompany;
        $scope.isExisting = !!model.id;

        $scope.saveAndNew = function () {
            $scope.save($scope.glAccount, true);
        };

        $scope.save = function (glAccount, saveAndNew) {
            $scope.saving = true;
            $scope.error = null;

            glAccount = angular.copy(glAccount);

            // parse parent account
            if (!$scope.isSubaccount) {
                glAccount.parent_id = null;
            } else if (glAccount.parent_id && typeof glAccount.parent_id == 'object') {
                glAccount.parent_id = glAccount.parent_id.id;
            }

            if ($scope.isExisting) {
                saveExisting(glAccount, saveAndNew);
            } else {
                saveNew(glAccount, saveAndNew);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function saveExisting(glAccount, saveAndNew) {
            GlAccount.edit(
                {
                    id: glAccount.id,
                },
                {
                    name: glAccount.name,
                    code: glAccount.code,
                    parent_id: glAccount.parent_id,
                },
                function () {
                    $scope.saving = false;
                    $modalInstance.close(saveAndNew);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function saveNew(glAccount, saveAndNew) {
            GlAccount.create(
                {},
                glAccount,
                function () {
                    $scope.saving = false;
                    $modalInstance.close(saveAndNew);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
