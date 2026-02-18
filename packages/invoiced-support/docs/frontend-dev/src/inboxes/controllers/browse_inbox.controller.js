(function () {
    'use strict';

    angular.module('app.inboxes').controller('BrowseInboxController', BrowseInboxController);

    BrowseInboxController.$inject = [
        '$scope',
        '$controller',
        '$rootScope',
        'Core',
        'EmailThread',
        '$stateParams',
        'CurrentUser',
        'UiFilterService',
    ];

    function BrowseInboxController(
        $scope,
        $controller,
        $rootScope,
        Core,
        EmailThread,
        $stateParams,
        CurrentUser,
        UiFilterService,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        $scope.model = EmailThread;

        $scope.loading = true;
        $scope.threads = [];
        $scope.activeEmail = false;

        $scope.inbox = $stateParams.id;
        $scope.currentUser = CurrentUser;

        $scope.$on('onThreadUpdate', function (event, thread) {
            angular.forEach($scope.threads, function (_thread, key) {
                if (_thread.id === thread.id) {
                    $scope.threads[key] = thread;
                    return false;
                }
            });
        });

        $scope.threadSelected = !!$stateParams.threadId;
        $scope.filterFields = filterFields;

        $scope.preFindAll = function () {
            return buildFindParams($scope.filter);
        };

        $scope.postFindAll = function (data) {
            $scope.threads = data;
        };

        $scope.isImage = function (file) {
            return file.type.split('/')[0] === 'image' && file.url;
        };

        $scope.reload = function () {
            $scope.loading = true;
            $scope.findAll();
            $scope.$broadcast('refreshInbox');
        };

        Core.setTitle('Inbox');
        $scope.initializeListPage();

        function filterFields() {
            return [
                {
                    id: 'status',
                    label: 'Status',
                    type: 'enum',
                    serialize: false,
                    defaultValue: 'open',
                    displayInFilterString: false,
                    values: [
                        { text: 'Open', value: 'open' },
                        { text: 'Pending', value: 'pending' },
                        { text: 'Closed', value: 'closed' },
                        { text: 'Sent', value: 'sent' },
                        { text: 'All', value: 'all' },
                    ],
                },
                {
                    id: 'created_at',
                    label: 'Created At',
                    type: 'datetime',
                },
                {
                    id: 'customer',
                    label: 'Customer',
                    type: 'customer',
                },
                {
                    id: 'assignee',
                    label: 'Assignee',
                    type: 'user',
                },
                {
                    id: 'name',
                    label: 'Name',
                    type: 'string',
                },
                {
                    id: 'updated_at',
                    label: 'Updated At',
                    type: 'datetime',
                },
                {
                    id: 'sort',
                    label: 'Sort',
                    type: 'sort',
                    defaultValue: 'created_at DESC',
                    values: [
                        { value: 'created_at ASC', text: 'Created Date, Oldest First' },
                        { value: 'created_at DESC', text: 'Created Date, Newest First' },
                    ],
                },
            ];
        }

        function buildFindParams(input) {
            let params = {
                inboxid: $scope.inbox,
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                expand: 'customer,assignee',
                include: 'cnt',
                sort: input.sort,
            };

            if ($scope.filter.status.value !== 'all') {
                params.status = $scope.filter.status.value;
            }

            return params;
        }
    }
})();
