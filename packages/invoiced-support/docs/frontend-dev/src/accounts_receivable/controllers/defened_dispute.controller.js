(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('DefendDisputeController', DefendDisputeController);

    DefendDisputeController.$inject = ['$scope', '$modalInstance', 'Core', 'Dispute', 'disputeObject'];

    function DefendDisputeController($scope, $modalInstance, Core, Dispute, disputeObject) {
        $scope.reasons = [];
        $scope.files = [];
        $scope.documents = [];
        $scope.reason = null;

        $scope.changeReason = function () {
            $scope.files = [];
            angular.forEach($scope.reason.defenseDocumentTypes, function (type) {
                if (type.requirementLevel === 'Required') {
                    $scope.files.push(type);
                }
            });
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.addFile = function () {
            $scope.files.push({});
        };

        $scope.removeFile = function (index) {
            $scope.files.splice(index, 1);
        };

        $scope.updateFile = function (file, index) {
            const uploaded = $scope.files[index].uploaded;
            $scope.files[index] = file;
            $scope.files[index].uploaded = uploaded;
        };

        $scope.defend = function () {
            Dispute.defendDispute(
                disputeObject.id,
                {
                    files: $scope.files,
                    reason_code: $scope.reason.defenseReasonCode,
                },
                function () {
                    $scope.close();
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        Dispute.getReasons(
            {
                id: disputeObject.id,
            },
            {},
            function (response) {
                $scope.reasons = response;
            },
            function (result) {
                Core.showMessage(result.data.message, 'error');
            },
        );
    }
})();
