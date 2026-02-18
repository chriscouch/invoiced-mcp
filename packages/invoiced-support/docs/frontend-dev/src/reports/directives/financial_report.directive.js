(function () {
    'use strict';

    angular.module('app.components').directive('financialReport', financialReport);

    financialReport.$inject = ['$filter'];

    function financialReport($filter) {
        return {
            restrict: 'E',
            scope: {
                data: '=',
            },
            link: function ($scope, element) {
                let group = $scope.data;
                let reportValue = $filter('reportValue');

                element.html(buildTable(group));

                function buildTable(group) {
                    let tableClass;
                    if (group.columns.length < 4) {
                        tableClass = 'font-large';
                    } else if (group.columns.length >= 4 && group.columns.length < 8) {
                        tableClass = 'font-medium';
                    } else if (group.columns.length >= 8) {
                        tableClass = 'font-small';
                    }

                    let html = '<table class="table ' + tableClass + '">\n';
                    html += buildHeader(group.columns);
                    html += buildBody(group);
                    html += '</table>\n';

                    return html;
                }

                function buildHeader(columns) {
                    let html = '<thead>\n';
                    html += '<tr>\n';
                    angular.forEach(columns, function (column) {
                        html += '<th class="type-' + column.type + '">' + column.name + '</th>';
                    });
                    html += '</tr>\n';
                    html += '</thead>\n';

                    return html;
                }

                function buildBody(group) {
                    let html = '<tbody>\n';
                    angular.forEach(group.rows, function (row) {
                        html += buildRow(row, group.columns, 0);
                    });
                    html += '</tbody>\n';

                    return html;
                }

                function buildRow(row, columns, level) {
                    if (row.type === 'data') {
                        return buildTableRow(row.columns, columns, level, 'row-data');
                    } else if (row.type === 'financial_rows') {
                        // Build header
                        let html = buildTableRow(row.header, columns, level, 'row-header');

                        // Build rows
                        angular.forEach(row.rows, function (row) {
                            html += buildRow(row, columns, level + 1);
                        });

                        // Build summary
                        html += buildTableRow(row.summary, columns, level, 'row-summary');

                        return html;
                    }
                }

                function buildTableRow(colData, columns, level, rowClass) {
                    if (colData.length === 0) {
                        return '';
                    }

                    let html = '<tr class="' + rowClass + ' level-' + level + '">\n';
                    angular.forEach(colData, function (value, index) {
                        html += buildTableCell(value, columns[index].type);
                    });
                    html += '</tr>\n';

                    return html;
                }

                function buildTableCell(value, type) {
                    return '<td class="type-' + type + '">' + reportValue(value) + '</td>';
                }
            },
        };
    }
})();
