(function () {
    'use strict';

    angular.module('app.components').controller('QRCodeController', QRCodeController);

    QRCodeController.$inject = ['$scope', '$modalInstance', '$timeout', 'QRCodeHelper', 'url'];

    function QRCodeController($scope, $modalInstance, $timeout, QRCodeHelper, url) {
        $scope.url = url;
        $timeout(() => {
            generateQrCode(url);
        });

        $scope.download = QRCodeHelper.download;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function generateQrCode(url) {
            const element = document.getElementById('qrcode');
            QRCodeHelper.generate(url, element);
        }
    }
})();
