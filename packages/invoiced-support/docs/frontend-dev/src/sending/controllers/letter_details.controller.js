(function () {
    'use strict';

    angular.module('app.sending').controller('LetterDetailsController', LetterDetailsController);

    LetterDetailsController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'Letter', 'event'];

    function LetterDetailsController($scope, $modalInstance, selectedCompany, Letter, event) {
        $scope.company = selectedCompany;
        $scope.event = event;

        angular.forEach($scope.event.message, function (part) {
            if (part.type == 'letter') {
                if (part.object == 'customer') {
                    $scope.customerId = part.object_id;
                } else if (part.object == 'invoice') {
                    $scope.invoiceId = part.object_id;
                } else if (part.object == 'estimate') {
                    $scope.estimateId = part.object_id;
                } else if (part.object == 'credit_note') {
                    $scope.creditNoteId = part.object_id;
                } else if (part.object == 'payment' || part.object == 'transaction') {
                    $scope.paymentId = part.object_id;
                }
            }
        });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        load($scope.event);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function load(event) {
            $scope.loading = true;

            Letter.find(
                {
                    id: event.data.object.id,
                },
                function (letter) {
                    $scope.letter = letter;
                    $scope.loading = false;
                },
                function () {
                    // could not load letter info
                    $scope.letter = false;
                    $scope.loading = false;
                },
            );
        }
    }
})();
