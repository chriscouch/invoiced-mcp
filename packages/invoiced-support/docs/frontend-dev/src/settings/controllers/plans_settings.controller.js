/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('PlansSettingsController', PlansSettingsController);

    PlansSettingsController.$inject = [
        '$scope',
        '$modal',
        'Plan',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
        'Feature',
    ];

    function PlansSettingsController($scope, $modal, Plan, LeavePageWarning, selectedCompany, Core, Feature) {
        $scope.hasFeature = Feature.hasFeature('subscription_billing');
        $scope.plans = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.editPlanModal = function (plan) {
            LeavePageWarning.block();

            plan = plan || false;

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-plan.html',
                controller: 'EditPlanController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    plan: function () {
                        if (!plan) {
                            return {
                                id: '',
                                currency: selectedCompany.currency,
                                interval: 'month',
                                interval_count: 1,
                                pricing_mode: 'per_unit',
                                tiers: [],
                                catalog_item: null,
                                metadata: {},
                            };
                        }

                        return plan;
                    },
                },
            });

            modalInstance.result.then(
                function (_plan) {
                    LeavePageWarning.unblock();

                    if (plan) {
                        angular.extend(plan, _plan);
                    } else {
                        $scope.plans.push(_plan);
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (plan) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this plan?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[plan.id] = true;
                        $scope.error = null;

                        Plan.delete(
                            {
                                id: plan.id,
                            },
                            function () {
                                $scope.deleting[plan.id] = false;

                                Core.flashMessage('The plan, ' + plan.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.plans) {
                                    if ($scope.plans[i].id == plan.id) {
                                        $scope.plans.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                Plan.clearCache();
                            },
                            function (result) {
                                $scope.deleting[plan.id] = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Plans');

        loadPlans();

        function loadPlans() {
            $scope.loading = true;

            Plan.all(
                function (plans) {
                    $scope.loading = false;
                    $scope.plans = plans;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
