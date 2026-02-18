/* globals Stripe */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('NewCardController', NewCardController);

    NewCardController.$inject = ['$scope', '$modalInstance', '$timeout', 'InvoicedConfig', 'Core', 'Card'];

    function NewCardController($scope, $modalInstance, $timeout, InvoicedConfig, Core, Card) {
        const stripeJsUrl = 'https://js.stripe.com/v3/';
        let stripe, cardElement, clientSecret;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.save = save;

        load();

        function load() {
            $scope.loading = true;

            // load the stripe.js script
            if (!InvoicedConfig.stripeJsLoaded) {
                $.getScript(stripeJsUrl, function () {
                    InvoicedConfig.stripeJsLoaded = true;
                    renderForm();
                });
            } else {
                $timeout(renderForm);
            }
        }

        function renderForm() {
            $scope.loading = false;
            stripe = Stripe(InvoicedConfig.stripePublishableKey);
            const elements = stripe.elements();
            cardElement = elements.create('card');
            cardElement.mount('#stripe-card-element');
        }

        function save() {
            $scope.saving = true;
            $scope.erorr = null;

            if (clientSecret) {
                finish(clientSecret);
            } else {
                Card.start(
                    function (result) {
                        clientSecret = result.client_secret;
                        finish(clientSecret);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data.message;
                    },
                );
            }
        }

        function finish(clientSecret) {
            stripe
                .confirmCardSetup(clientSecret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: $scope.cardholderName,
                        },
                    },
                })
                .then(result => {
                    if (result.error) {
                        $scope.$apply(() => {
                            $scope.error = result.error.message;
                            $scope.saving = false;
                        });
                    } else if (result.setupIntent.status === 'succeeded') {
                        Card.finish(
                            {
                                setup_intent: result.setupIntent.id,
                            },
                            function (card) {
                                $scope.saving = false;
                                $modalInstance.close(card);
                            },
                            function (result) {
                                $scope.saving = false;
                                $scope.error = result.data.message;
                            },
                        );
                    } else {
                        $scope.$apply(() => {
                            $scope.saving = false;
                        });
                    }
                });
        }
    }
})();
