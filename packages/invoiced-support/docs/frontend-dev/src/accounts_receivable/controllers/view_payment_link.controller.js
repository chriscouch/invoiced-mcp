/* globals Clipboard */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewPaymentLinkController', ViewPaymentLinkController);

    ViewPaymentLinkController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        '$modal',
        '$timeout',
        'PaymentLink',
        'PaymentLinkHelper',
        'Core',
        'BrowsingHistory',
        'Money',
        'selectedCompany',
        'QRCodeHelper',
    ];

    function ViewPaymentLinkController(
        $scope,
        $controller,
        $rootScope,
        $modal,
        $timeout,
        PaymentLink,
        PaymentLinkHelper,
        Core,
        BrowsingHistory,
        Money,
        selectedCompany,
        QRCodeHelper,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = PaymentLink;
        $scope.modelTitleSingular = 'Payment Link';
        $scope.modelObjectType = 'payment_link';

        //
        // Presets
        //

        let actionItems = [];
        $scope.tab = 'summary';
        $scope.completedSessions = [];

        $timeout(function () {
            const clipboard = new Clipboard('.btn-copy');

            clipboard.on('success', function () {
                $scope.$apply(function () {
                    $scope.copied = true;
                });
            });
        });

        //
        // Methods
        //

        $scope.preFind = function (findParams) {
            findParams.expand = 'customer';
            findParams.include = 'items,fields';
        };

        $scope.postFind = function (paymentLink) {
            $scope.paymentLink = paymentLink;
            $scope.customerName = paymentLink.customer ? paymentLink.customer.name : null;

            $rootScope.modelTitle = paymentLink.name;
            Core.setTitle(paymentLink.name);

            // compute the action items
            computeActionItems();

            $scope.paymentLinkTotal = PaymentLinkHelper.calculateTotalPrice(paymentLink);

            BrowsingHistory.push({
                id: paymentLink.id,
                type: 'payment_link',
                title:
                    paymentLink.name +
                    ': ' +
                    Money.currencyFormat($scope.paymentLinkTotal, paymentLink.currency, selectedCompany.moneyFormat),
            });

            $scope.completedSessions = [];
            PaymentLink.completedSessions(
                {
                    id: paymentLink.id,
                    expand: 'customer,invoice',
                },
                function (sessions) {
                    $scope.completedSessions = sessions;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );

            return $scope.paymentLink;
        };

        $scope.deleteMessage = function () {
            return '<p>Are you sure you want to delete this payment link?</p>';
        };

        $scope.postDelete = function () {
            // override delete method so we do not redirect
            $scope.paymentLink.status = 'deleted';
            computeActionItems();
        };

        $scope.hasActionItem = function (k) {
            return actionItems.indexOf(k) !== -1;
        };

        /* Customer Portal */

        $scope.urlModal = function (url) {
            $modal.open({
                templateUrl: 'components/views/url-modal.html',
                controller: 'URLModalController',
                resolve: {
                    url: function () {
                        return url;
                    },
                    customer: function () {
                        return null;
                    },
                },
            });
        };

        $scope.qrCode = QRCodeHelper.openModal;

        $scope.urlParameters = function (paymentLink) {
            $modal.open({
                templateUrl: 'accounts_receivable/views/payment-links/template-parameters.html',
                controller: 'PaymentLinkTemplateParameters',
                resolve: {
                    paymentLink: function () {
                        return paymentLink;
                    },
                },
            });
        };

        //
        // Initialization
        //

        $scope.initializeDetailPage();
        Core.setTitle('Payment Link');

        function computeActionItems() {
            let paymentLink = $scope.paymentLink;

            // deleted
            if (paymentLink.status === 'deleted') {
                actionItems = ['deleted'];
                return;
            }

            let items = [];

            // completed
            if (paymentLink.status === 'completed') {
                items.push('completed');
            }

            // active
            if (paymentLink.status === 'active') {
                items.push('active');
            }

            actionItems = items;
        }
    }
})();
