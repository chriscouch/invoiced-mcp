(function () {
    'use strict';

    angular.module('app.settings').controller('CancelAccountController', CancelAccountController);

    CancelAccountController.$inject = ['$scope', '$state', 'Company', 'CurrentUser', 'CSRF', 'selectedCompany', 'Core'];

    function CancelAccountController($scope, $state, Company, CurrentUser, CSRF, selectedCompany, Core) {
        if (selectedCompany.test_mode) {
            $state.go('manage.settings.default');
        }

        $scope.company = angular.copy(selectedCompany);

        $scope.cancelAccount = function () {
            $scope.saving = true;

            // retrieve a fresh CSRF token first
            CSRF(function () {
                Company.cancelAccount(
                    {
                        id: $scope.company.id,
                        password: $scope.cancelPassword,
                        why: $scope.why,
                        reason: $scope.reason,
                    },
                    function (result) {
                        $scope.saving = false;

                        if (result.canceled_now) {
                            canceledNow();
                        } else {
                            canceledAtPeriodEnd();
                        }
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            });
        };

        Core.setTitle('Cancel Account');

        function canceledNow() {
            Core.showMessage('The account for ' + $scope.company.name + ' has been canceled.', 'success');

            // Switch to another non-canceled company if one exists
            let switched = false;
            angular.forEach(CurrentUser.companies, function (company) {
                if (!company.canceled && company.id != $scope.company.id && !switched) {
                    CurrentUser.useCompany(company);
                    $state.go('index');
                    switched = true;
                }
            });

            // Otherwise sign the user out
            if (!switched) {
                $state.go('auth.logout');
            }
        }

        function canceledAtPeriodEnd() {
            Core.showMessage(
                'The account for ' +
                    $scope.company.name +
                    ' will be canceled at the end of the current billing period.',
                'success',
            );

            // Just go back to the billing page
            $state.go('manage.settings.billing');
        }
    }
})();
