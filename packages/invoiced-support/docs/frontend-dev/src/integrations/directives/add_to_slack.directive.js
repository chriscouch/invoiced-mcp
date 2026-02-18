(function () {
    'use strict';

    angular.module('app.integrations').directive('addToSlack', addToSlack);

    function addToSlack() {
        return {
            restrict: 'E',
            template:
                '<a ng-href="{{connectUrl}}">' +
                '<img alt="Add to Slack" height="40" width="139" src="/img/add_to_slack@2x.png" />' +
                '</a>',
            scope: {},
            controller: [
                '$scope',
                'AppDirectory',
                'selectedCompany',
                function ($scope, AppDirectory, selectedCompany) {
                    $scope.connectUrl = AppDirectory.get('slack').connectUrl + '?company=' + selectedCompany.id;
                },
            ],
        };
    }
})();
