(function () {
    'use strict';

    angular.module('app.components').directive('usageBar', usageBar);

    function usageBar() {
        return {
            restrict: 'E',
            templateUrl: 'components/views/usage-bar.html',
            scope: {
                amount: '=',
                limit: '=',
                usagePricingPlan: '=',
                options: '=?',
                callback: '&',
                dimension: '=',
            },
            controller: [
                '$scope',
                '$modal',
                '$window',
                'selectedCompany',
                function ($scope, $modal, $window, selectedCompany) {
                    let defaultOptions = {
                        warning: 75,
                        alarm: 95,
                        name: '',
                        namePlural: '',
                    };

                    $scope.options = $scope.options || {};

                    $scope.close = function () {
                        $modal.dismiss('cancel');
                    };

                    $scope.userCountModal = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'user_management/views/user-count-editor.html',
                            controller: 'UserCountEditorController',
                            size: 'md',
                            backdrop: 'static',
                            keyboard: false,
                            resolve: {
                                limit: function () {
                                    return $scope.limit;
                                },
                                usagePricingPlan: function () {
                                    return $scope.usagePricingPlan;
                                },
                            },
                        });

                        modalInstance.result.then(function () {
                            // reload the page
                            $window.location.reload();
                        });
                    };

                    let update = function () {
                        $scope.options = angular.extend({}, defaultOptions, $scope.options);

                        if ($scope.limit <= 0) {
                            $scope.percent = 0;
                        } else {
                            $scope.percent = Math.min(($scope.amount / $scope.limit) * 100, 100);
                        }

                        $scope.name = $scope.amount == 1 ? $scope.options.name : $scope.options.namePlural;

                        if ($scope.percent >= $scope.options.alarm) {
                            $scope.level = 'danger';
                        } else if ($scope.percent >= $scope.options.warning) {
                            $scope.level = 'warning';
                        } else {
                            $scope.level = 'success';
                        }

                        $scope.canAddUsers =
                            $scope.dimension === 'users' &&
                            $scope.limit > 0 &&
                            selectedCompany.billing.provider !== 'reseller' &&
                            selectedCompany.billing.provider !== 'null' &&
                            selectedCompany.billing.status !== 'trialing' &&
                            $scope.usagePricingPlan;
                    };

                    update();

                    $scope.$watch('amount', update);
                    $scope.$watch('limit', update);
                },
            ],
        };
    }
})();
