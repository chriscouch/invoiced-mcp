/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('CreditCardsSettingsController', CreditCardsSettingsController);

    CreditCardsSettingsController.$inject = [
        '$scope',
        '$modal',
        'Core',
        'LeavePageWarning',
        'Card',
        'PaymentDisplayHelper',
    ];

    function CreditCardsSettingsController($scope, $modal, Core, LeavePageWarning, Card, PaymentDisplayHelper) {
        $scope.cards = [];
        $scope.deleting = [];

        Core.setTitle('Cards');
        load();

        $scope.new = newCard;
        $scope.delete = deleteCard;

        function load() {
            $scope.loading = true;
            Card.findAll(
                function (cards) {
                    $scope.loading = false;
                    $scope.cards = cards;
                    angular.forEach(cards, function (card) {
                        parseCard(card);
                    });
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function newCard() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/cards/new.html',
                controller: 'NewCardController',
                backdrop: 'static',
                keyboard: false,
            });

            modalInstance.result.then(
                function (card) {
                    Core.flashMessage('Card successfully saved', 'success');
                    LeavePageWarning.unblock();
                    parseCard(card);
                    $scope.cards.push(card);
                },
                function () {
                    LeavePageWarning.unblock();
                    // canceled
                },
            );
        }

        function deleteCard(item, $index) {
            $scope.deleting[item.id] = true;
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this card?',
                callback: function (result) {
                    if (result) {
                        Card.delete(
                            {
                                id: item.id,
                            },
                            {},
                            function () {
                                $scope.deleting[item.id] = false;
                                Core.flashMessage('Card successfully deleted', 'success');
                                $scope.cards.splice($index, 1);
                            },
                            function (result) {
                                $scope.deleting[item.id] = false;
                                Core.showMessage(result.data, 'error');
                            },
                        );
                    }
                },
            });
        }

        function parseCard(card) {
            card.name = PaymentDisplayHelper.formatCard(card.brand, card.last4);
        }
    }
})();
