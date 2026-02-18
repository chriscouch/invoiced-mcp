(function () {
    'use strict';

    angular.module('app.content').directive('helpIcon', helpIcon);

    function helpIcon() {
        return {
            restrict: 'E',
            template:
                '<div class="help-icon">' +
                '<a ui-sref="manage.help" title="Help and Support" ng-click="clickHelp()">' +
                '<span class="fas fa-question"></span>' +
                '</a>' +
                '</div>',
            controller: [
                '$scope',
                '$window',
                'localStorageService',
                function ($scope, $window, localStorageService) {
                    $scope.clickHelp = function () {
                        localStorageService.add('helpCurrentPage', $window.location.href);
                    };
                },
            ],
        };
    }
})();
