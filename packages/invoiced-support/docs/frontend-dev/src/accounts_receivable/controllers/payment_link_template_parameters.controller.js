/* globals Clipboard */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('PaymentLinkTemplateParameters', PaymentLinkTemplateParameters);

    PaymentLinkTemplateParameters.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        '$translate',
        'QRCodeHelper',
        'paymentLink',
    ];

    function PaymentLinkTemplateParameters($scope, $modalInstance, $timeout, $translate, QRCodeHelper, paymentLink) {
        $scope.paymentLink = paymentLink;
        $scope.parameters = {
            clientId: '',
            amount: '',
            firstName: '',
            lastName: '',
            company: '',
            email: '',
            address: '',
            address2: '',
            city: '',
            state: '',
            zip: '',
            country: '',
            phone: '',
            invoice_number: '',
            description: '',
        };
        $scope.url = buildUrl(paymentLink.url, $scope.parameters);

        $timeout(function () {
            setToBeginning();

            let clipboard = new Clipboard('.btn-copy');

            clipboard.on('success', function () {
                $scope.$apply(function () {
                    $scope.copied = true;
                });
            });
        });

        $scope.qrCode = QRCodeHelper.openModal;

        $scope.changeCountry = function (country) {
            let locale = 'en_' + country;
            $scope.cityLabel = $translate.instant('address.city', {}, null, locale);
            $scope.stateLabel = $translate.instant('address.state', {}, null, locale);
            $scope.postalCodeLabel = $translate.instant('address.postal_code', {}, null, locale);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.$watch(
            'parameters',
            function () {
                $scope.url = buildUrl(paymentLink.url, $scope.parameters);
            },
            true,
        );

        $scope.changeCountry('');

        function buildUrl(url, parameters) {
            angular.forEach(parameters, function (value, key) {
                if (value) {
                    if (!url.includes('?')) {
                        url += '?';
                    } else {
                        url += '&';
                    }

                    url += key + '=' + encodeURIComponent(value);
                }
            });

            return url;
        }

        function setToBeginning() {
            // set cursor to beginning of textbox
            document.getElementById('clipboard-url').setSelectionRange(0, 0);
        }
    }
})();
