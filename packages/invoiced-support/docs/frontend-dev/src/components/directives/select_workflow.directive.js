(function () {
    'use strict';

    angular.module('app.catalog').directive('selectWorkflow', selectWorkflow);

    selectWorkflow.$inject = ['$filter'];

    function selectWorkflow($filter) {
        return {
            restrict: 'E',
            template:
                '<input type="hidden" ng-model="workflow" ui-select2="options" ng-hide="loading" ng-required="isRequired" />' +
                '<div class="loading inline" ng-show="loading"></div>',
            scope: {
                workflow: '=ngModel',
                isRequired: '=?',
            },
            controller: [
                '$scope',
                'Core',
                'ApprovalWorkflow',
                function ($scope, Core, ApprovalWorkflow) {
                    let escapeHtml = $filter('escapeHtml');
                    $scope.options = {
                        data: {
                            results: [],
                            text: 'name',
                        },
                        initSelection: function (element, callback) {
                            callback($scope.workflow);
                        },
                        formatSelection: function (workflow) {
                            return escapeHtml(workflow.name);
                        },
                        formatResult: function (workflow) {
                            return "<div class='title'>" + escapeHtml(workflow.name) + '</div>';
                        },
                        placeholder: 'Select a workflow',
                        width: '100%',
                    };

                    ApprovalWorkflow.all(
                        {
                            'filter[enabled]': 1,
                        },
                        function (workflows) {
                            let data = [];
                            if (!$scope.isRequired) {
                                data.push({
                                    id: null,
                                    name: 'Not Assigned',
                                    text: 'Not Assigned',
                                });
                            }
                            angular.forEach(workflows, function (workflow) {
                                let _workflow = angular.copy(workflow);
                                // the text is the searchable part
                                _workflow.text = workflow.name;
                                data.push(_workflow);
                            });

                            $scope.options.data.results = data;
                        },
                        function (error) {
                            Core.showMessage(error.message, 'error');
                        },
                    );
                },
            ],
        };
    }
})();
