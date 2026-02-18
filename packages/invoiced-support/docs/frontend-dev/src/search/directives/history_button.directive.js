(function () {
    'use strict';

    angular.module('app.search').directive('historyButton', historyButton);

    function historyButton() {
        return {
            restrict: 'E',
            template:
                '<div class="history-button dropdown" dropdown>' +
                '<a href="" class="btn btn-link dropdown-toggle" dropdown-toggle ng-click="loadHistory()" title="Recently Viewed">' +
                '<img src="/img/icons/history.svg" alt="Recently Viewed"/>' +
                '</a>' +
                '<ul class="dropdown-menu">' +
                '<li class="title">Recently Viewed</li>' +
                '<li class="no-history" ng-show="history.length===0">No recent history</li>' +
                '<li ng-repeat="entry in history">' +
                '<a href="" ng-click="goTo(entry)">' +
                '<div class="icon"><img ng-src="/img/event-icons/{{entry.type}}.png" /></div>' +
                '<div class="name">{{entry.title}}</div>' +
                '</a>' +
                '</li>' +
                '</ul>' +
                '</div>',
            controller: [
                '$scope',
                '$state',
                'BrowsingHistory',
                'ObjectDeepLink',
                function ($scope, $state, BrowsingHistory, ObjectDeepLink) {
                    $scope.loadHistory = function () {
                        $scope.history = BrowsingHistory.history();
                    };

                    $scope.goTo = function (entry) {
                        if (entry.type === 'email') {
                            $state.go('manage.inboxes.browse.view_thread', {
                                id: entry.inboxId,
                                threadId: entry.id,
                                status: entry.status,
                            });
                        } else {
                            ObjectDeepLink.goTo(entry.type, entry.id);
                        }
                    };
                },
            ],
        };
    }
})();
