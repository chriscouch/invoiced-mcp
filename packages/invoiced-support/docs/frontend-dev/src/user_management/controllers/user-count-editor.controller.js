(function () {
    'use strict';

    angular.module('app.user_management').controller('UserCountEditorController', UserCountEditorController);

    UserCountEditorController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'CurrentUser',
        'Company',
        'Notification',
        'limit',
        'usagePricingPlan',
    ];

    function UserCountEditorController(
        $scope,
        $modalInstance,
        selectedCompany,
        CurrentUser,
        Company,
        Notification,
        limit,
        usagePricingPlan,
    ) {
        $scope.company = angular.copy(selectedCompany);
        $scope.included = usagePricingPlan.threshold;
        $scope.originalLimit = limit - $scope.included;
        $scope.newLimit = $scope.originalLimit;
        $scope.perUserCost = usagePricingPlan.unit_price;

        $scope.generateCost = function (count) {
            $scope.newTotal = count * $scope.perUserCost;
        };

        $scope.changeUserCount = function (count) {
            $scope.saving = true;
            Company.changeUserCount(
                {
                    id: $scope.company.id,
                },
                { count: count },
                function () {
                    usagePricingPlan.threshold = count;
                    $scope.saving = false;
                    $modalInstance.close();
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
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

        $scope.generateCost($scope.newLimit);
    }
})();
