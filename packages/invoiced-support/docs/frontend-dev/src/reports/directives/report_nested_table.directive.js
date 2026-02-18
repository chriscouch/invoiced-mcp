(function () {
    'use strict';

    angular.module('app.components').directive('reportNestedTable', reportNestedTable);

    reportNestedTable.$inject = [];

    function reportNestedTable() {
        return {
            restrict: 'E',
            template:
                '<table class="table" id="nested-table-{{tableId}}" ng-class="{\'font-large\': table.columns.length < 4, \'font-medium\': table.columns.length >= 4 && table.columns.length < 8, \'font-small\': table.columns.length >= 8}">' +
                '<thead>' +
                '<tr>' +
                '<th class="type-{{column.type}}" ng-repeat="column in table.columns">{{column.name}}</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody></tbody>' +
                '<tfoot ng-if="table.footer">' +
                '<tr>' +
                '<td class="type-{{table.columns[$index].type}}" ng-repeat="value in table.footer.columns track by $index" ng-bind-html="value|reportValue"></td>' +
                '</tr>' +
                '</tfoot>' +
                '</table>',
            scope: {
                table: '=table',
            },
            controller: [
                '$scope',
                '$filter',
                '$timeout',
                function ($scope, $filter, $timeout) {
                    $scope.tableId = Math.floor(Math.random() * 10000);
                    let reportValueFilter = $filter('reportValue');
                    let escapeHtml = $filter('escapeHtml');

                    // build the table HTML
                    $timeout(function () {
                        let bodyHtml = renderTable($scope.table);

                        // add to the table
                        let tableEl = $('#nested-table-' + $scope.tableId);
                        $('tbody', tableEl).html(bodyHtml);
                        $('.collapse-btn a', tableEl).on('click', function () {
                            let header = $(this).parents('.section-group-header');
                            let groupId = header.data('group');
                            if (header.hasClass('collapsed')) {
                                header.removeClass('collapsed');
                                $('.g-' + groupId).removeClass('hidden');
                            } else {
                                header.addClass('collapsed');
                                $('.g-' + groupId).addClass('hidden');
                            }
                        });
                    });

                    function renderTable(table, columns, level, groupClass) {
                        let html = '';
                        columns = columns || table.columns;
                        level = level || 0;
                        groupClass = groupClass || '';
                        let thisGroup;
                        if (level > 0) {
                            thisGroup = Math.floor(Math.random() * 100000);
                        }

                        // group name
                        if (level > 0 && table.group) {
                            html +=
                                '<tr class="section-group-header l-' +
                                level +
                                ' ' +
                                groupClass +
                                '" data-group="' +
                                thisGroup +
                                '">';
                            html += '<td class="type-' + table.group.type + '" colspan="' + columns.length + '">';
                            html +=
                                '<div class="collapse-btn"><a href="#"><span class="arrow up"><span class="fas fa-chevron-up"></span></span><span class="arrow down"><span class="fas fa-chevron-down"></span></span></a></div>';
                            html += '<div class="header">';
                            html += '<div class="name">' + escapeHtml(table.group.name) + '</div>';
                            html += '<div class="value">' + reportValueFilter(table.group.value) + '</div></div>';
                            html += '</td></tr>';
                            groupClass += ' g-' + thisGroup;
                        }

                        // Repeat the header on every group when grouping is used
                        if (level > 0 && table.group && !table.header) {
                            table.header = { columns: columns };
                        }

                        // header
                        if (table.header) {
                            if (table.rows.length > 0 && table.rows[0].type === 'data') {
                                // only add header above data rows
                                html += '<tr class="section-header l-' + level + '" >';
                                angular.forEach(table.header.columns, function (column, j) {
                                    html +=
                                        '<td class="type-' +
                                        columns[j].type +
                                        '">' +
                                        reportValueFilter(column) +
                                        '</td>';
                                });
                                html += '</tr>';
                            }
                        }

                        angular.forEach(table.rows, function (row, i) {
                            if (row.type === 'data') {
                                let stripe = i % 2 === 1 ? 'even' : 'odd';
                                html +=
                                    '<tr class="data-line l-' + level + ' ' + groupClass + ' stripe-' + stripe + '">';
                                angular.forEach(row.columns, function (column, j) {
                                    html +=
                                        '<td class="type-' +
                                        columns[j].type +
                                        '">' +
                                        reportValueFilter(column) +
                                        '</td>';
                                });
                                html += '</tr>';
                            } else if (row.type === 'nested_table') {
                                html += renderTable(row, columns, level + 1, groupClass);
                            }
                        });

                        // footer
                        if (level > 0 && table.footer) {
                            html += '<tr class="section-footer l-' + level + '">';
                            angular.forEach(table.footer.columns, function (column, j) {
                                html +=
                                    '<td class="type-' + columns[j].type + '">' + reportValueFilter(column) + '</td>';
                            });
                            html += '</tr>';
                        }

                        return html;
                    }
                },
            ],
        };
    }
})();
