/* globals moment */
(function () {
    'use strict';

    angular.module('app.collections').controller('BrowseTasksController', BrowseTasksController);

    BrowseTasksController.$inject = [
        '$scope',
        '$rootScope',
        '$state',
        '$modal',
        '$controller',
        '$filter',
        '$q',
        '$translate',
        'CurrentUser',
        'Task',
        'Member',
        'Core',
        'selectedCompany',
        'ColumnArrangementService',
        'UiFilterService',
        'AutomationWorkflow',
    ];

    function BrowseTasksController(
        $scope,
        $rootScope,
        $state,
        $modal,
        $controller,
        $filter,
        $q,
        $translate,
        CurrentUser,
        Task,
        Member,
        Core,
        selectedCompany,
        ColumnArrangementService,
        UiFilterService,
        AutomationWorkflow,
    ) {
        $controller('BaseModelController', {
            $scope: $scope,
        });

        //
        // Settings
        //

        $scope.model = Task;
        $scope.modelTitleSingular = 'Task';
        $scope.modelTitlePlural = 'Tasks';

        $scope.tasks = [];
        $scope.myUserId = CurrentUser.profile.id;
        $scope.allColumns = ColumnArrangementService.getColumnsFromConfig('task');

        //
        // Methods
        //

        $scope.loadSettings = function () {
            $scope.columns = ColumnArrangementService.getSelectedColumns('task', $scope.allColumns);
        };
        $scope.loadSettings();
        loadAutomations();

        $scope.preFindAll = function () {
            return buildFindParams($scope.filter);
        };

        $scope.postFindAll = function (tasks) {
            let now = moment();
            let notePreviewLength = 50;
            angular.forEach(tasks, function (task) {
                if (task.user_id) {
                    task.user_id.name = task.user_id.first_name + ' ' + task.user_id.last_name;
                }

                // build note preview
                let note = task.most_recent_note || '';
                task.most_recent_note_expands = note.length > notePreviewLength;
                task.most_recent_note_preview =
                    note.substr(0, notePreviewLength) + (task.most_recent_note_expands ? '...' : '');
                task._expandNote = false;

                // tally up balance and num invoices
                task.balance = 0;
                task.balance_count = 0;
                task.currency =
                    task.customer && task.customer.currency ? task.customer.currency : selectedCompany.currency;
                angular.forEach(task.aging, function (row) {
                    task.balance += row.amount;
                    task.balance_count += row.count;
                });

                // determine the due date relative time string
                let dueDate = moment.unix(task.due_date);
                task.past_due = !task.complete && (dueDate.isSame(now, 'day') || dueDate.isBefore(now, 'day'));
            });
            $scope.tasks = tasks;
        };

        $scope.filterFields = function () {
            return [
                {
                    id: 'action',
                    label: 'Action',
                    type: 'enum',
                    values: [
                        {
                            value: 'approve_bill',
                            text: 'Approve Bill',
                        },
                        {
                            value: 'approve_vendor_credit',
                            text: 'Approve Vendor Credit',
                        },
                        {
                            value: 'email',
                            text: 'Email',
                        },
                        {
                            value: 'letter',
                            text: 'Letter',
                        },
                        {
                            value: 'phone',
                            text: 'Phone Call',
                        },
                        {
                            value: 'review',
                            text: 'Review',
                        },
                    ],
                },
                {
                    id: 'automation',
                    label: 'Automation',
                    type: 'enum',
                    values: $scope.automations,
                    serialize: false,
                },
                {
                    id: 'completed_date',
                    label: 'Completed Date',
                    type: 'date',
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
                    id: 'due_date',
                    label: 'Due Date',
                    type: 'date',
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
                    id: 'user_id',
                    label: 'Assignee',
                    type: 'user',
                    defaultValue: CurrentUser.profile.id,
                    displayInFilterString: function (filter) {
                        return (
                            (filter.user_id && filter.user_id.value !== CurrentUser.profile.id) ||
                            filter.complete === '1'
                        );
                    },
                },
                {
                    id: 'complete',
                    label: 'Completed',
                    type: 'boolean',
                    defaultValue: '0',
                    displayInFilterString: false,
                },
            ];
        };

        $scope.noResults = function () {
            return $scope.tasks.length === 0;
        };

        $scope.newTask = function () {
            const modalInstance = $modal.open({
                templateUrl: 'collections/views/tasks/edit.html',
                controller: 'EditTaskController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return false;
                    },
                    task: function () {
                        return false;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('The task has been created.', 'success');
                    $scope.findAll();
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.edit = function (task) {
            const modalInstance = $modal.open({
                templateUrl: 'collections/views/tasks/edit.html',
                controller: 'EditTaskController',
                size: 'sm',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    customer: function () {
                        return false;
                    },
                    task: function () {
                        return task;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    Core.flashMessage('The task has been edited.', 'success');
                    $scope.findAll();
                },
                function () {
                    // canceled
                },
            );
        };

        $scope.markComplete = function (task, complete) {
            $scope.saving = true;
            $scope.error = null;
            Task.edit(
                {
                    id: task.id,
                },
                {
                    complete: complete,
                    completed_by_user_id: CurrentUser.profile.id,
                },
                function () {
                    $scope.saving = false;
                    $scope.findAll();
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.saving = false;
                },
            );
        };

        $scope.deleteMessage = function (task) {
            return (
                '<p>Are you sure you want to delete this task?</p>' +
                '<p><strong>' +
                $filter('escapeHtml')(task.name) +
                '</strong></p>'
            );
        };

        //
        // Initialization
        //

        $scope.initializeListPage();
        Core.setTitle('Activities');

        function buildFindParams(input) {
            return {
                filter: {},
                advanced_filter: UiFilterService.serializeFilter(input, $scope._filterFields),
                include: 'aging,most_recent_note',
                sort: input.sort,
            };
        }

        function loadAutomations() {
            AutomationWorkflow.loadAutomations(
                'task',
                automations => {
                    $scope.automations = automations;
                    $scope.generateFilterFields();
                    $scope.updateFilterString();
                },
                () => {},
            );
        }
    }
})();
