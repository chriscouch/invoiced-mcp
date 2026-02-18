/* globals moment */
(function () {
    'use strict';

    angular.module('app.reports').controller('ReportBuilderController', ReportBuilderController);

    ReportBuilderController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$modal',
        '$log',
        '$timeout',
        'Core',
        'ReportBuilder',
        'Report',
        'LeavePageWarning',
    ];

    function ReportBuilderController(
        $scope,
        $state,
        $stateParams,
        $modal,
        $log,
        $timeout,
        Core,
        ReportBuilder,
        Report,
        LeavePageWarning,
    ) {
        //
        // Models
        //

        $scope.report = {
            version: 1,
            title: 'My Report',
            sections: [],
        };
        addSection();
        $scope.reportParameters = {};

        //
        // Settings
        //

        $scope.reportObjects = [];
        $scope.selectedColumns = [];

        $scope.sortableOptions = {
            handle: '.sortable-handle',
            placholder: 'sortable-placeholder',
        };

        $scope.cmRefresh = 0;
        $scope.cmOptions = {
            theme: 'monokai',
            lineNumbers: true,
            lineWrapping: true,
            indentWithTabs: true,
            tabSize: 2,
            matchBrackets: true,
            styleActiveLine: false,
            mode: 'javascript',
        };

        //
        // Methods
        //

        $scope.addSection = addSection;
        $scope.moveSectionUp = moveSectionUp;
        $scope.moveSectionDown = moveSectionDown;
        $scope.deleteSection = deleteSection;
        $scope.makeAdvanced = makeAdvanced;
        $scope.makeSimple = makeSimple;
        $scope.changeObject = changeObject;
        $scope.changeVisualization = changeVisualization;
        $scope.addColumns = addColumns;
        $scope.changeColumnName = changeColumnName;
        $scope.deleteColumn = deleteColumn;
        $scope.addFilterCondition = addFilterCondition;
        $scope.deleteFilterCondition = deleteFilterCondition;
        $scope.addGroupField = addGroupField;
        $scope.deleteGroupField = deleteGroupField;
        $scope.addSortField = addSortField;
        $scope.deleteSortField = deleteSortField;
        $scope.generateReport = build;

        //
        // Initialization
        //

        $scope.loading = true;
        ReportBuilder.initialize(function (reportObjects) {
            $scope.reportObjects = reportObjects;

            if ($stateParams.id) {
                loadReport($stateParams.id);
            } else {
                $scope.loading = false;
            }
        });

        Core.setTitle('Report Builder');
        LeavePageWarning.watchForm($scope, 'reportBuilderForm');

        function loadReport(id) {
            Report.find(
                {
                    id: id,
                },
                function (report) {
                    $scope.loading = false;
                    if (report.definition) {
                        angular.extend($scope.report, ReportBuilder.loadDefinition(report.definition));

                        $timeout(function () {
                            // force a CM UI refresh after the page loads
                            $scope.cmRefresh++;
                        });
                    }
                    if (report.parameters) {
                        if (typeof report.parameters.$dateRange !== 'undefined') {
                            report.parameters.$dateRange.start = moment(
                                report.parameters.$dateRange.start,
                                'YYYY-MM-DD',
                            ).toDate();
                            report.parameters.$dateRange.end = moment(
                                report.parameters.$dateRange.end,
                                'YYYY-MM-DD',
                            ).toDate();
                            if (typeof report.parameters.$dateRange.period === 'undefined') {
                                report.parameters.$dateRange.period = 'custom';
                            }
                        }

                        $scope.reportParameters = report.parameters;
                    }
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function addSection() {
            $scope.report.sections.push({
                title: 'Section ' + ($scope.report.sections.length + 1),
                object: 'customer',
                type: 'table',
                chart_type: 'bar',
                multi_entity: false,
                fields: [],
                filter: [],
                group: [],
                sort: [],
                _advancedMode: false,
                _advancedInput: '',
            });
        }

        function moveSectionUp($index) {
            $scope.report.sections.splice($index - 1, 0, $scope.report.sections.splice($index, 1)[0]);
        }

        function moveSectionDown($index) {
            $scope.report.sections.splice($index + 1, 0, $scope.report.sections.splice($index, 1)[0]);
        }

        function deleteSection(index) {
            $scope.report.sections.splice(index, 1);
        }

        function makeAdvanced(section) {
            section._advancedMode = true;
            try {
                section._advancedInput = JSON.stringify(ReportBuilder.buildRequestSection(section), null, 2);
            } catch (e) {
                $log.error(e);
                section._advancedInput = '';
            }
        }

        function makeSimple(section) {
            section._advancedMode = false;

            let parsedDefinition;
            try {
                parsedDefinition = angular.fromJson(section._advancedInput);
            } catch (e) {
                parsedDefinition = {};
                $log.error(e);
            }

            angular.extend(section, ReportBuilder.loadDefinitionSection(parsedDefinition));
        }

        function changeObject(section) {
            // changing the reporting object must clear out the form because available fields and IDs will be different
            changeVisualization(section);
            section.filter = [];
            section.group = [];
            section.sort = [];
        }

        function changeVisualization(section) {
            section.fields = [];
            section.group = [];
            if (section.type === 'chart') {
                addBlankColumn(section);
                addBlankColumn(section);
            } else if (section.type === 'metric') {
                addBlankColumn(section);
                section.sort = [];
            }
        }

        function addBlankColumn(section) {
            section.fields.push({
                name: '',
                _field: '',
                _function: '',
                _meta: {
                    type: 'string',
                    name: '',
                },
            });
        }

        function addColumns(section) {
            const modalInstance = $modal.open({
                templateUrl: 'reports/views/field-selector.html',
                controller: 'ReportFieldSelectorController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    options: function () {
                        return { multiple: true };
                    },
                    excludedObjects: function () {
                        return [];
                    },
                    object: function () {
                        return section.object;
                    },
                },
            });

            modalInstance.result.then(
                function (fields) {
                    angular.forEach(fields, function (field) {
                        // only allow adding up to N columns
                        if (section.fields.length >= 25) {
                            return;
                        }

                        // prevent adding duplicates
                        let fieldId = field.id;
                        for (let i in section.fields) {
                            if (section.fields[i]._field === fieldId) {
                                return;
                            }
                        }

                        let column = {
                            name: '',
                            _field: fieldId,
                            _function: '',
                            _meta: {
                                type: 'string',
                                name: '',
                            },
                        };
                        ReportBuilder.updateFieldMeta(section.object, column);
                        section.fields.push(column);
                    });
                },
                function () {
                    // canceled
                },
            );
        }

        function changeColumnName(column) {
            column.name = column._meta.name;
        }

        function deleteColumn(section, index) {
            section.fields.splice(index, 1);
        }

        function addFilterCondition(section) {
            section.filter.push({
                _field: '',
                operator: '',
                value: '',
                _meta: {
                    type: 'string',
                    name: '',
                },
            });
        }

        function deleteFilterCondition(section, index) {
            section.filter.splice(index, 1);
        }

        function addGroupField(section) {
            section.group.push({
                _field: '',
                sort_direction: 'asc',
                expanded: true,
                _meta: {
                    type: 'string',
                    name: '',
                },
            });
        }

        function deleteGroupField(section, index) {
            section.group.splice(index, 1);
        }

        function addSortField(section) {
            section.sort.push({
                _field: '',
                sort_direction: 'asc',
                _meta: {
                    type: 'string',
                    name: '',
                },
            });
        }

        function deleteSortField(section, index) {
            section.sort.splice(index, 1);
        }

        function build(report) {
            let definition;
            try {
                definition = ReportBuilder.buildRequest(report);
            } catch (e) {
                Core.showMessage(e.message || e, 'error');
                return;
            }

            let reportParameters = ReportBuilder.determineParameters(definition);
            for (let i in $scope.reportParameters) {
                if (typeof reportParameters[i] !== 'undefined') {
                    reportParameters[i] = $scope.reportParameters[i];
                }
            }
            if (Object.keys(reportParameters).length === 0) {
                generate(definition, {});
                return;
            }

            const modalInstance = $modal.open({
                templateUrl: 'reports/views/parameters.html',
                controller: 'ReportParametersController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    parameters: function () {
                        return reportParameters;
                    },
                },
            });

            modalInstance.result.then(
                function (parameters) {
                    generate(definition, parameters);
                },
                function () {
                    // canceled
                },
            );
        }

        function generate(definition, parameters) {
            $scope.building = true;
            Report.create(
                {
                    definition: definition,
                    parameters: parameters,
                },
                function (_report) {
                    $scope.building = false;
                    LeavePageWarning.unblock();
                    $state.go('manage.report.view', { id: _report.id });
                },
                function (result) {
                    $scope.building = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
