(function () {
    'use strict';

    angular.module('app.payment_setup').directive('flywirePaymentsOffer', flywirePaymentsOffer);

    function flywirePaymentsOffer() {
        return {
            restrict: 'E',
            templateUrl: 'payment_setup/views/flywire-payments-offer.html',
            scope: {
                canDismiss: '=',
            },
            controller: [
                '$scope',
                '$modal',
                'localStorageService',
                'Permission',
                'FlywirePayments',
                function ($scope, $modal, localStorageService, Permission, FlywirePayments) {
                    $scope.eligibility = { eligible: false };

                    // Only show to administrators
                    if (!Permission.hasPermission('settings.edit')) {
                        return;
                    }

                    // Check if the user has previously dismissed this alert
                    if ($scope.canDismiss && localStorageService.get('dismissedFlywirePaymentsBanner2')) {
                        return;
                    }

                    $scope.learnMore = function () {
                        $modal.open({
                            templateUrl: 'payment_setup/views/flywire-payments-learn-more.html',
                            controller: 'FlywirePaymentsLearnMoreController',
                            size: 'lg',
                            resolve: {
                                onboardingUrl: () => {
                                    return $scope.eligibility.onboarding_url;
                                },
                                pricing: () => {
                                    return $scope.eligibility.pricing;
                                },
                            },
                        });
                    };

                    $scope.dismiss = function () {
                        $scope.eligibility = { eligible: false };
                        localStorageService.set('dismissedFlywirePaymentsBanner2', true);
                    };

                    loadEligibility();

                    function loadEligibility() {
                        FlywirePayments.eligibility(
                            function (result) {
                                $scope.eligibility = result;
                            },
                            function () {
                                // do nothing on error
                            },
                        );
                    }
                },
            ],
        };
    }
})();
