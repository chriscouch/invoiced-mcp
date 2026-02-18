/* globals console, QRCode */
(function () {
    'use strict';

    angular.module('app.components').factory('QRCodeHelper', QRCodeHelper);

    QRCodeHelper.$inject = ['$modal', 'Core'];

    function QRCodeHelper($modal, Core) {
        return {
            download: download,
            generate: generate,
            openModal: openModal,
        };

        function generate(url, element) {
            QRCode.toCanvas(element, url, function (error) {
                if (error) {
                    console.error(error);
                }
            });
        }

        function download(url) {
            QRCode.toDataURL(url, function (error, dataUrl) {
                if (error) {
                    console.error(error);
                } else {
                    Core.downloadUrl(dataUrl, 'qr.png');
                }
            });
        }

        function openModal(url) {
            $modal.open({
                templateUrl: 'components/views/qr-code.html',
                controller: 'QRCodeController',
                windowClass: 'qr-code-modal',
                resolve: {
                    url: function () {
                        return url;
                    },
                },
            });
        }
    }
})();
