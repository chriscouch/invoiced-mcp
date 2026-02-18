(function () {
    'use strict';

    angular.module('app.dashboard').directive('dashboardSetupChecklist', dashboardSetupChecklist);

    function dashboardSetupChecklist() {
        return {
            restrict: 'E',
            templateUrl: 'dashboard/views/components/setup-checklist.html',
            scope: {
                context: '=',
            },
            controller: [
                '$scope',
                'localStorageService',
                'CurrentUser',
                'selectedCompany',
                'Company',
                'CSRF',
                'Core',
                function ($scope, localStorageService, CurrentUser, selectedCompany, Company, CSRF, Core) {
                    $scope.user = CurrentUser.profile;
                    $scope.company = selectedCompany;
                    // decide if the welcome message should be shown
                    $scope.showWelcome = true;
                    $scope.setupTab = 'branding';
                    $scope.setup = {};
                    let setupOrder = ['branding', 'payments', 'import', 'verify', 'send', 'accounting', 'team'];

                    $scope.hideWelcome = function () {
                        $scope.showWelcome = false;
                    };

                    $scope.markSetupComplete = function () {
                        localStorageService.set('completedSetup.' + selectedCompany.id, true);
                        $scope.showWelcome = false;
                    };

                    $scope.resendVerification = function () {
                        $scope.resending = true;
                        $scope.error = null;

                        // retrieve a fresh CSRF token first
                        CSRF(function () {
                            Company.resendVerificationEmail(
                                {
                                    id: selectedCompany.id,
                                },
                                {},
                                function () {
                                    $scope.resending = false;
                                    $scope.resent = true;
                                    Core.flashMessage(
                                        'A new verification request has been sent to ' + selectedCompany.email,
                                        'success',
                                    );
                                },
                                function (result) {
                                    $scope.resending = false;
                                    $scope.error = result.data;
                                },
                            );
                        });
                    };

                    checkSetupSteps();

                    function checkSetupSteps() {
                        if (!$scope.showWelcome) {
                            return;
                        }

                        Company.setupProgress(
                            function (result) {
                                $scope.setup = result;

                                // Find the next incomplete step
                                let hasIncompleteStep = false;
                                for (let i in setupOrder) {
                                    let step = setupOrder[i];
                                    if (!$scope.setup[step]) {
                                        $scope.setupTab = step;
                                        hasIncompleteStep = true;
                                        break;
                                    }
                                }

                                if (!hasIncompleteStep) {
                                    $scope.showWelcome = false;
                                }
                            },
                            function () {
                                // do nothing on error
                            },
                        );
                    }
                },
            ],
        };
    }
})();
