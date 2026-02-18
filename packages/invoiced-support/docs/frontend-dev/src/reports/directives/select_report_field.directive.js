(function () {
    'use strict';

    angular.module('app.components').directive('selectReportField', selectReportField);

    selectReportField.$inject = [];

    function selectReportField() {
        return {
            restrict: 'E',
            template:
                '<div class="report-field-control">\n' +
                '<a href="" ng-click="selectColumn()" class="select-field" ng-hide="field._field">Select Field</a>' +
                '<a href="" ng-click="selectColumn()" class="change-field" ng-if="field && field._field">{{field._meta.name}} <span class="icon"><span class="fas fa-pencil"></span></span></a>' +
                '</div>',
            scope: {
                object: '=',
                field: '=ngModel',
            },
            controller: [
                '$scope',
                '$modal',
                'ReportBuilder',
                function ($scope, $modal, ReportBuilder) {
                    $scope.selectColumn = function () {
                        const modalInstance = $modal.open({
                            templateUrl: 'reports/views/field-selector.html',
                            controller: 'ReportFieldSelectorController',
                            backdrop: 'static',
                            keyboard: false,
                            size: 'lg',
                            resolve: {
                                options: function () {
                                    return { multiple: false };
                                },
                                excludedObjects: function () {
                                    return [];
                                },
                                object: function () {
                                    return $scope.object;
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (fields) {
                                let field = {
                                    _field: fields[0].id,
                                    _function: '',
                                    _meta: {
                                        type: 'string',
                                        name: '',
                                    },
                                };

                                if (typeof $scope.field === 'object') {
                                    angular.extend($scope.field, field);
                                } else {
                                    $scope.field = field;
                                }

                                ReportBuilder.updateFieldMeta($scope.object, $scope.field);
                            },
                            function () {
                                // canceled
                            },
                        );
                    };
                },
            ],
        };
    }
})();
