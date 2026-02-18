/* globals moment */
(function () {
    'use strict';

    angular.module('app.imports').directive('importRecordValue', importRecordValue);

    function importRecordValue() {
        return {
            restrict: 'E',
            template: '<div class="import-record" ng-bind-html="html"></div>',
            scope: {
                value: '=',
            },
            controller: [
                '$scope',
                '$filter',
                'Core',
                'selectedCompany',
                function ($scope, $filter, Core, selectedCompany) {
                    let escapeHtml = $filter('escapeHtml');
                    $scope.html = build($scope.value);

                    function build(value, propertyName) {
                        let html, i;

                        if (angular.isArray(value)) {
                            if (value.length === 0) {
                                return '<span class="empty-list">Empty</span>';
                            }

                            html = '<div class="array-list">';
                            for (i in value) {
                                html += '<div class="array-item">' + build(value[i]) + '</div>';
                            }
                            html += '</div>';

                            return html;
                        } else if (typeof value === 'object' && value) {
                            let keys = Object.keys(value).sort();
                            if (keys.length === 0) {
                                return '<span class="empty-list">Empty</span>';
                            }

                            html = '<div class="object-list">';
                            for (i in keys) {
                                let key = keys[i];
                                html +=
                                    '<div class="object-item">' +
                                    '<span class="key">' +
                                    escapeHtml(key) +
                                    '</span>' +
                                    '<div class="value">' +
                                    build(value[key], key) +
                                    '</div>' +
                                    '</div>';
                            }
                            html += '</div>';

                            return html;
                        } else {
                            propertyName = propertyName || '';

                            // look for date properties to format
                            if (
                                propertyName === 'date' ||
                                propertyName === 'due_date' ||
                                propertyName === 'timestamp' ||
                                propertyName === 'expiration_date'
                            ) {
                                if (typeof value === 'number') {
                                    return moment
                                        .unix(value)
                                        .format(Core.phpDateFormatToMoment(selectedCompany.date_format));
                                }
                            }

                            return escapeHtml(value);
                        }
                    }
                },
            ],
        };
    }
})();
