(function () {
    'use strict';

    angular.module('app.catalog').controller('AddPlanController', AddPlanController);

    AddPlanController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$filter',
        '$timeout',
        'Plan',
        'Core',
        'selectedCompany',
        'currency',
        'interval',
        'interval_count',
        'multiple',
        'filter',
    ];

    function AddPlanController(
        $scope,
        $modalInstance,
        $modal,
        $filter,
        $timeout,
        Plan,
        Core,
        selectedCompany,
        currency,
        interval,
        interval_count,
        multiple,
        filter,
    ) {
        $scope.plans = [];
        $scope.plansMap = {};
        $scope.selected = [];
        $scope.selectedMap = {};
        $scope.company = selectedCompany;
        $scope.multiple = multiple;

        $scope.newPlanModal = function (name) {
            $('.add-plan-modal').hide();

            name = name || '';

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-plan.html',
                controller: 'EditPlanController',
                backdrop: false,
                keyboard: false,
                resolve: {
                    plan: function () {
                        return {
                            id: '',
                            currency: currency,
                            interval: interval,
                            interval_count: interval_count,
                            pricing_mode: 'per_unit',
                            tiers: [],
                            catalog_item: null,
                            metadata: {},
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (newPlan) {
                    $scope.plans.push(newPlan);
                    $scope.plansMap[newPlan.id] = newPlan;
                    $scope.togglePlan(newPlan);
                    $('.add-plan-modal').show();
                },
                function () {
                    // canceled
                    $('.add-plan-modal').show();
                },
            );
        };

        $scope.togglePlan = function (plan) {
            // select a single plan if multiple selection is disabled
            if (!$scope.multiple) {
                $modalInstance.close([plan]);
                return;
            }

            // add to the selected list
            if (typeof $scope.selectedMap[plan.id] == 'undefined' || !$scope.selectedMap[plan.id]) {
                $scope.selected.push(plan.id);
                $scope.selectedMap[plan.id] = true;
                // remove from the selected list
            } else {
                $scope.selected.splice($scope.selected.indexOf(plan.id), 1);
                $scope.selectedMap[plan.id] = false;
            }
        };

        $scope.isSelected = function (plan) {
            return $scope.selectedMap[plan.id];
        };

        $scope.addPlans = function (selected) {
            let plans = [];
            angular.forEach(selected, function (id) {
                plans.push($scope.plansMap[id]);
            });

            $modalInstance.close(plans);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        loadPlans();

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function loadPlans() {
            $scope.loading = true;

            Plan.all(
                function (plans) {
                    // restrict the plans available to the given currency / interval
                    angular.forEach(plans, function (plan) {
                        if (
                            plan.currency == currency &&
                            (!interval || plan.interval == interval) &&
                            (!interval_count || plan.interval_count == interval_count) &&
                            filter(plan)
                        ) {
                            $scope.plans.push(plan);
                            $scope.plansMap[plan.id] = plan;
                        }
                    });

                    $scope.loading = false;

                    // focus searchbar input (after timeout so DOM can render)
                    $timeout(function () {
                        $('.modal-selector .search input').focus();
                    }, 50);
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
