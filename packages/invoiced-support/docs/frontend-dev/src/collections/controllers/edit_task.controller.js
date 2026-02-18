/* globals moment */
(function () {
    'use strict';

    angular.module('app.collections').controller('EditTaskController', EditTaskController);

    EditTaskController.$inject = [
        '$scope',
        '$modalInstance',
        '$timeout',
        'Member',
        'Task',
        'CurrentUser',
        'Core',
        'task',
        'customer',
        'DatePickerService',
    ];

    function EditTaskController(
        $scope,
        $modalInstance,
        $timeout,
        Member,
        Task,
        CurrentUser,
        Core,
        task,
        customer,
        DatePickerService,
    ) {
        if (task) {
            $scope.task = angular.copy(task);
            $scope.task.due_date = moment.unix(task.due_date).toDate();
        } else {
            $scope.isNew = true;
            $scope.task = {
                name: '',
                action: 'review',
                due_date: new Date(),
                customer_id: customer || null,
                user_id: {
                    id: CurrentUser.profile.id,
                },
            };
        }

        $scope.dateOptions = DatePickerService.getOptions();

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };

        $scope.save = function (task) {
            task = angular.copy(task);

            if (task.customer_id && task.customer_id.id > 0) {
                task.customer_id = task.customer_id.id;
            } else {
                task.customer_id = null;
            }

            if (task.user_id && task.user_id.id > 0) {
                task.user_id = task.user_id.id;
            } else {
                task.user_id = null;
            }

            task.due_date = moment(task.due_date).unix();

            if (task.id) {
                edit(task);
            } else {
                create(task);
            }
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        load();

        function load() {
            $scope.loading = true;

            Member.dropDownList(
                function (members) {
                    $scope.users = members;
                    $scope.loading = false;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function create(task) {
            $scope.saving = true;
            Task.new(
                {
                    name: task.name,
                    action: task.action,
                    due_date: task.due_date,
                    customer_id: task.customer_id,
                    user_id: task.user_id,
                },
                function (_task) {
                    $scope.saving = false;
                    $modalInstance.close(_task);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function edit(task) {
            $scope.saving = true;
            Task.edit(
                {
                    id: task.id,
                },
                {
                    name: task.name,
                    action: task.action,
                    due_date: task.due_date,
                    user_id: task.user_id,
                },
                function (_task) {
                    $scope.saving = false;
                    $modalInstance.close(_task);
                },
                function (result) {
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
