(function () {
    'use strict';
    angular.module('app.inboxes').directive('email', email);

    function email() {
        return {
            restrict: 'E',
            templateUrl: 'inboxes/views/email.html',
            scope: {
                email: '=',
                thread: '=',
                toLabel: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.collapsed = false;
                },
            ],
        };
    }
})();
