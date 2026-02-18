(function () {
    'use strict';

    angular.module('app.sending').controller('SendDocumentNetworkController', SendDocumentNetworkController);

    SendDocumentNetworkController.$inject = [
        '$scope',
        '$modalInstance',
        'Network',
        'customer',
        'documentId',
        'alreadySent',
        'model',
        'options',
    ];

    function SendDocumentNetworkController(
        $scope,
        $modalInstance,
        Network,
        customer,
        documentId,
        alreadySent,
        model,
        options,
    ) {
        $scope.customer = customer;
        $scope.invite = { to: customer.email };
        $scope.isInvited = false;
        $scope.alreadySent = alreadySent;

        $scope.send = send;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        load();

        function load() {
            if (customer.network_connection) {
                // If there is already a connection and the document has never been sent, then send the document.
                if (!alreadySent) {
                    send($scope.invite);
                }
                return;
            }

            Network.invitations(
                {
                    'filter[customer]': customer.id,
                },
                function (results) {
                    $scope.isInvited = results.length > 0;
                },
                function (result) {
                    $scope.error = result.data;
                },
            );
        }

        function send(invite) {
            $scope.sending = true;
            $scope.error = null;

            if (customer.network_connection) {
                sendDocument(documentId, options, 'Woot! Your document has been sent.');
            } else if (!$scope.isInvited) {
                Network.sendInvite(
                    {
                        to: invite.to,
                        customer: customer.id,
                    },
                    function () {
                        sendDocument(
                            documentId,
                            options,
                            'Your customer has been sent a vendor approval request. Once they accept your invitation this document will be sent.',
                        );
                    },
                    function (result) {
                        $scope.sending = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                sendDocument(
                    documentId,
                    options,
                    'Your document is queued to be sent once your customer accepts your invitation.',
                );
            }
        }

        function sendDocument(documentId, options, msg) {
            model.sendInvoiced(
                {
                    id: documentId,
                },
                options,
                function () {
                    $scope.sending = false;
                    $modalInstance.close(msg);
                },
                function (result) {
                    $scope.sending = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
