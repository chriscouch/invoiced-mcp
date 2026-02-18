(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('invoiceTags', invoiceTags);

    invoiceTags.inject = ['$filter'];

    function invoiceTags($filter) {
        let escapeHtml = $filter('escapeHtml');
        return {
            restrict: 'E',
            template: '<input type="hidden" ng-model="tags" ui-select2="tagSelectOptions" />',
            scope: {
                tags: '=ngModel',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.tagSelectOptions = {
                        multiple: true,
                        simple_tags: true,
                        tags: [],
                        maximumSelectionSize: 10,
                        minimumInputLength: 1,
                        maximumInputLength: 50,
                        tokenSeparators: [',', ' ', ';'],
                        width: '100%',
                        placeholder: 'Enter any tags',
                        formatNoMatches: function () {
                            return 'No match found';
                        },
                        formatResult: function (item) {
                            return '<span class="create">Add <span>' + escapeHtml(item.text) + '</span> tag</span>';
                        },
                    };
                },
            ],
        };
    }
})();
