/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('GlAccountsSettingsController', GlAccountsSettingsController);

    GlAccountsSettingsController.$inject = [
        '$scope',
        '$modal',
        '$timeout',
        'Company',
        'GlAccount',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
    ];

    function GlAccountsSettingsController(
        $scope,
        $modal,
        $timeout,
        Company,
        GlAccount,
        LeavePageWarning,
        selectedCompany,
        Core,
    ) {
        $scope.company = angular.copy(selectedCompany);

        $scope.glAccounts = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.editGlAccountModal = function (glAccount) {
            LeavePageWarning.block();

            glAccount = glAccount || false;

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-gl-account.html',
                controller: 'EditGlAccountController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return glAccount;
                    },
                },
            });

            modalInstance.result.then(
                function (saveAndNew) {
                    LeavePageWarning.unblock();

                    if (saveAndNew) {
                        $scope.editGlAccountModal();
                    } else {
                        loadGlAccounts();
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (glAccount) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this G/L account?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[glAccount.id] = true;
                        $scope.error = null;

                        GlAccount.delete(
                            {
                                id: glAccount.id,
                            },
                            function () {
                                $scope.deleting[glAccount.id] = false;

                                Core.flashMessage(
                                    'The G/L account, ' + glAccount.name + ', has been deleted',
                                    'success',
                                );

                                // remove locally
                                for (let i in $scope.glAccounts) {
                                    if ($scope.glAccounts[i].id == glAccount.id) {
                                        $scope.glAccounts.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                GlAccount.clearCache();
                            },
                            function (result) {
                                $scope.deleting[glAccount.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('G/L Accounts');

        loadGlAccounts();

        function loadGlAccounts() {
            $scope.loading = true;

            GlAccount.all(
                function (glAccounts) {
                    $scope.loading = false;
                    $scope.glAccounts = glAccounts;
                },
                function (message) {
                    $scope.loading = false;
                    Core.showMessage(message, 'error');
                },
            );
        }
    }
})();
