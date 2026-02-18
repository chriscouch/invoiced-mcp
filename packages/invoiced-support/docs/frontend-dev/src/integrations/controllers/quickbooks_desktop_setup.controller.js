(function () {
    'use strict';

    angular.module('app.integrations').controller('QuickBooksDesktopSetupController', QuickBooksDesktopSetupController);

    QuickBooksDesktopSetupController.$inject = ['$scope', 'Integration', '$modalInstance'];

    function QuickBooksDesktopSetupController($scope, Integration, $modalInstance) {
        $scope.generating = 0;

        $scope.generate = generate;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function generate() {
            $scope.generating = true;
            Integration.connect(
                {
                    id: 'quickbooks_desktop',
                },
                {},
                function (result) {
                    $scope.generating = false;
                    $scope.qwcConfig = result;
                    $scope.generated = true;
                },
                function (result) {
                    $scope.generating = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
