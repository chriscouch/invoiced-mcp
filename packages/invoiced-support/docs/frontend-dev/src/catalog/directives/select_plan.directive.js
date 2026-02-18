(function () {
    'use strict';

    angular.module('app.catalog').directive('selectPlan', selectPlan);

    selectPlan.$inject = ['$filter', 'InvoicedConfig'];

    function selectPlan($filter, InvoicedConfig) {
        let escapeHtml = $filter('escapeHtml');

        return {
            restrict: 'E',
            template:
                '<input type="hidden" ng-model="plan" ui-select2="options" ng-hide="loading" required />' +
                '<div class="loading inline" ng-show="loading"></div>',
            scope: {
                plan: '=ngModel',
                watch: '=',
                createNew: '=?',
                filter: '=?',
            },
            controller: [
                '$scope',
                '$filter',
                '$modal',
                'Plan',
                'Money',
                'selectedCompany',
                function ($scope, $filter, $modal, Plan, Money, selectedCompany) {
                    if ($scope.watch) {
                        $scope.$watch('plan', $scope.watch, true);
                    }

                    $scope.options = {
                        data: {
                            results: [],
                            text: 'name',
                        },
                        initSelection: function (element, callback) {
                            let id = $(element).val();
                            if (id) {
                                let jqxhr = $.ajax({
                                    url: InvoicedConfig.apiBaseUrl + '/plans/' + id,
                                    method: 'GET',
                                    dataType: 'json',
                                    headers: {
                                        Authorization: selectedCompany.auth_header,
                                    },
                                    xhrFields: {
                                        withCredentials: false,
                                    },
                                });

                                jqxhr
                                    .done(function (result) {
                                        callback(result);
                                    })
                                    .fail(function () {
                                        callback(null);
                                    });
                            }
                        },
                        formatSelection: function (plan) {
                            return escapeHtml(plan.name);
                        },
                        formatResult: function (plan) {
                            let amount = Money.currencyFormat(
                                plan.amount,
                                plan.currency,
                                selectedCompany.moneyFormat,
                                true,
                            );

                            if (plan.pricing_mode === 'tiered') {
                                amount = 'Tiered';
                            } else if (plan.pricing_mode === 'volume') {
                                amount = 'Volume';
                            } else if (plan.pricing_mode === 'custom') {
                                amount = 'Custom';
                            }

                            return (
                                "<div class='title'>" +
                                escapeHtml(plan.name) +
                                '</div>' +
                                "<div class='details'>" +
                                amount +
                                ' ' +
                                $filter('recurringFrequency')(plan.interval_count, plan.interval) +
                                '</div>'
                            );
                        },
                        placeholder: 'Select a plan',
                        width: '100%',
                    };

                    // Load all plans
                    Plan.all(function (plans) {
                        let data = [];
                        angular.forEach(plans, function (plan) {
                            let _plan = angular.copy(plan);
                            // the text is the searchable part
                            _plan.text = [plan.name, plan.id].join(' ');
                            data.push(_plan);
                        });

                        // filter results based on filter provided
                        // as directive attribute
                        if ($scope.filter != null) {
                            data = data.filter($scope.filter);
                        }

                        $scope.options.data.results = data;
                    });

                    let newPlanModal = function () {
                        $('.modal').hide();

                        const modalInstance = $modal.open({
                            templateUrl: 'catalog/views/edit-plan.html',
                            controller: 'EditPlanController',
                            resolve: {
                                plan: function () {
                                    return {
                                        id: '',
                                        currency: selectedCompany.currency,
                                        pricing_mode: 'per_unit',
                                        tiers: [],
                                        interval: 'month',
                                        interval_count: 1,
                                        catalog_item: null,
                                        metadata: {},
                                    };
                                },
                            },
                            backdrop: false,
                            keyboard: false,
                        });

                        modalInstance.result.then(
                            function (plan) {
                                $scope.plan = plan;
                                $scope.options.data.results.push(plan);

                                $('.modal').show();
                            },
                            function () {
                                // canceled
                                $('.modal').show();
                            },
                        );
                    };

                    // Watch for changes to createNew
                    $scope.$watch('createNew', function (newValue, oldValue) {
                        if (typeof newValue === 'undefined') {
                            return;
                        }

                        if (newValue && newValue != oldValue) {
                            newPlanModal();
                        }
                    });
                },
            ],
        };
    }
})();
