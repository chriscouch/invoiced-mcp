/* globals Clipboard */
(function () {
    'use strict';

    angular.module('app.components').controller('URLModalController', URLModalController);

    URLModalController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        '$modal',
        'Core',
        'CustomerGenericEmail',
        'url',
        'customer',
    ];

    function URLModalController($scope, $modalInstance, $timeout, $modal, Core, CustomerGenericEmail, url, customer) {
        $scope.url = url;
        $scope.customer = customer;

        $timeout(function () {
            setToBeginning();

            let clipboard = new Clipboard('.btn-copy');

            clipboard.on('success', function () {
                $scope.$apply(function () {
                    $scope.copied = true;
                });
            });
        });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.sendModal = function () {
            const modalInstance = $modal.open({
                templateUrl: 'sending/views/send-document.html',
                controller: 'SendDocumentController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                windowClass: 'send-document-modal',
                resolve: {
                    model: function () {
                        return CustomerGenericEmail;
                    },
                    _document: function () {
                        return $scope.customer;
                    },
                    paymentPlan: function () {
                        return {};
                    },
                    customerId: function () {
                        return $scope.customer.id;
                    },
                    sendOptions: function () {
                        return {
                            url: url,
                        };
                    },
                },
            });

            modalInstance.result.then(function (result) {
                Core.flashMessage(result, 'success');
                $modalInstance.close();
            });
        };

        function setToBeginning() {
            // set cursor to beginning of textbox
            document.getElementById('clipboard-url').setSelectionRange(0, 0);
        }
    }
})();
