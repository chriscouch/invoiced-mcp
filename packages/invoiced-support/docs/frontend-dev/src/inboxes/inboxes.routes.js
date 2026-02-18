(function () {
    'use strict';

    angular.module('app.inboxes').config(routes);

    routes.$inject = ['$stateProvider'];

    function routes($stateProvider) {
        $stateProvider
            .state('manage.ar_inbox', {
                url: '/ar_inbox',
                controller: [
                    '$state',
                    'Settings',
                    function ($state, Settings) {
                        Settings.accountsReceivable(function (settings) {
                            if (settings.inbox) {
                                $state.go('manage.inboxes.browse', {
                                    id: settings.inbox,
                                });
                            }
                        });
                    },
                ],
            })
            .state('manage.ap_inbox', {
                url: '/ap_inbox',
                controller: [
                    '$state',
                    'Settings',
                    function ($state, Settings) {
                        Settings.accountsPayable(function (settings) {
                            if (settings.inbox) {
                                $state.go('manage.inboxes.browse', {
                                    id: settings.inbox,
                                });
                            }
                        });
                    },
                ],
            })
            .state('manage.inboxes', {
                abstract: true,
                url: '/inboxes',
                template: '<ui-view/>',
            })
            .state('manage.inboxes.browse', {
                url: '?id',
                templateUrl: 'inboxes/views/browse-inbox.html',
                controller: 'BrowseInboxController',
            })
            .state('manage.inboxes.browse.view_thread', {
                url: '/thread/:threadId?emailId',
                templateUrl: 'inboxes/views/inbox-thread.html',
                controller: 'ViewEmailThreadController',
            });
    }
})();
