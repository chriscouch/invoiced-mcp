(function () {
    'use strict';

    angular.module('app.network').directive('networkDocument', networkDocument);

    function networkDocument() {
        return {
            restrict: 'E',
            templateUrl: 'network/views/network-document.html',
            scope: {
                doc: '=',
                transactions: '=?',
                balance: '=?',
            },
            controller: [
                '$scope',
                'NetworkDocument',
                'Core',
                function ($scope, NetworkDocument, Core) {
                    $scope.download = function (doc, pdf) {
                        $scope.downloading = true;
                        NetworkDocument.download(
                            doc.id,
                            pdf,
                            function (data, filename) {
                                $scope.downloading = false;
                                Core.createAndDownloadBlobFile(data, filename);
                            },
                            function (error) {
                                $scope.downloading = false;
                                Core.showMessage(error.message, 'error');
                            },
                        );
                    };
                },
            ],
        };
    }
})();
