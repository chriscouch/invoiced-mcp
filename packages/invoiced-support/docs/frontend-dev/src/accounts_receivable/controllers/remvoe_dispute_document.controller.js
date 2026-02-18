(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('RemoveDisputeDocumentsController', RemoveDisputeDocumentsController);

    RemoveDisputeDocumentsController.$inject = ['$scope', '$modalInstance', 'Core', 'Dispute', 'disputeObject'];

    function RemoveDisputeDocumentsController($scope, $modalInstance, Core, Dispute, disputeObject) {
        $scope.reasons = [];
        $scope.code = null;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.remove = function () {
            Dispute.deleteDisputeFile(
                {
                    id: disputeObject.id,
                    fileCode: $scope.code.defenseDocumentTypeCode,
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
                for (const i in response) {
                    if (response[i].defenseReasonCode === disputeObject.defense_reason) {
                        $scope.reasons = response[i].defenseDocumentTypes;

                        return;
                    }
                }
            },
            function (result) {
                Core.showMessage(result.data.message, 'error');
            },
        );
    }
})();
