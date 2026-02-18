(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('UploadDisputeDocumentsController', UploadDisputeDocumentsController);

    UploadDisputeDocumentsController.$inject = ['$scope', '$modalInstance', 'Core', 'Dispute', 'disputeObject'];

    function UploadDisputeDocumentsController($scope, $modalInstance, Core, Dispute, disputeObject) {
        $scope.reasons = [];
        $scope.code = null;
        $scope.file = null;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.upload = function () {
            Dispute.uploadDisputeFile(
                disputeObject.id,
                {
                    file: $scope.file,
                    code: $scope.code.defenseDocumentTypeCode,
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
