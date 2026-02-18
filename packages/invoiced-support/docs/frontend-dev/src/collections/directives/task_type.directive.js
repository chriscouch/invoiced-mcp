(function () {
    'use strict';

    angular.module('app.collections').directive('taskType', taskType);

    function taskType() {
        let taskTypes = {
            email: 'Send an email',
            letter: 'Send a letter',
            phone: 'Phone call',
            approve_bill: 'Approve bill',
        };

        return {
            restrict: 'E',
            template:
                '<a class="task-icon" href="" tooltip-placement="right" tooltip="{{type}}" ng-if="type">' +
                '<span ng-if="task.action==\'phone\'"><span class="fad fa-phone-volume"></span></span>' +
                '<span ng-if="task.action==\'email\'"><span class="fad fa-envelope-open-text"></span></span>' +
                '<span ng-if="task.action==\'letter\'"><span class="fad fa-mailbox"></span></span>' +
                '<span ng-if="task.action==\'approve_bill\'"><span class="icon icon-bills"></span></span>' +
                '</a>',
            scope: {
                task: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    if (typeof $scope.task === 'object' && typeof taskTypes[$scope.task.action] !== 'undefined') {
                        $scope.type = taskTypes[$scope.task.action];
                    }
                },
            ],
        };
    }
})();
