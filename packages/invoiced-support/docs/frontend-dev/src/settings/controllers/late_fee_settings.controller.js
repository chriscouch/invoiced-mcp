/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('LateFeeSettingsController', LateFeeSettingsController);

    LateFeeSettingsController.$inject = [
        '$scope',
        '$modal',
        'Core',
        'LateFeeSchedule',
        'LeavePageWarning',
        'selectedCompany',
    ];

    function LateFeeSettingsController($scope, $modal, Core, LateFeeSchedule, LeavePageWarning, selectedCompany) {
        $scope.currency = selectedCompany.currency;

        $scope.edit = function (schedule) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'settings/views/edit-late-fee-schedule.html',
                controller: 'EditLateFeeSchedulesController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    schedule: function () {
                        return schedule;
                    },
                },
            });

            modalInstance.result.then(
                function (r) {
                    LeavePageWarning.unblock();

                    if (schedule && schedule.id) {
                        Core.flashMessage('Late fee schedule was updated', 'success');
                        angular.extend(schedule, r);
                    } else {
                        Core.flashMessage('Late fee schedule was created', 'success');
                        $scope.schedules.push(r);
                    }
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.duplicate = function (schedule) {
            schedule = angular.copy(schedule);
            delete schedule.id;
            $scope.edit(schedule);
        };

        $scope.setProperty = function (schedule, property, value) {
            $scope.deleting = true;
            $scope.error = null;
            let params = {};
            params[property] = value;

            LateFeeSchedule.edit(
                {
                    id: schedule.id,
                },
                params,
                function (_schedule) {
                    angular.extend(schedule, _schedule);
                    $scope.deleting = false;
                    Core.flashMessage('The schedule, ' + schedule.name + ', has been updated', 'success');
                },
                function (result) {
                    $scope.deleting = false;
                    $scope.error = result.data;
                },
            );
        };

        $scope.run = function (schedule) {
            $scope.error = null;
            vex.dialog.confirm({
                message:
                    'Are you sure you want to assess late fees now? Invoiced does this for you automatically once per day.',
                callback: function (result) {
                    if (result) {
                        LateFeeSchedule.run(
                            {
                                id: schedule.id,
                            },
                            {},
                            function () {
                                schedule.last_run = 'Now';
                                Core.flashMessage(
                                    'The schedule, ' + schedule.name + ', has been queued up and will begin shortly.',
                                    'success',
                                );
                            },
                            function (result) {
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        $scope.delete = function (schedule) {
            vex.dialog.confirm({
                message: 'Are you sure you want to remove this late fee schedule?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting = true;
                        LateFeeSchedule.delete(
                            {
                                id: schedule.id,
                            },
                            function () {
                                $scope.deleting = false;
                                for (let i in $scope.schedules) {
                                    if ($scope.schedules[i].id === schedule.id) {
                                        $scope.schedules.splice(i, 1);
                                        break;
                                    }
                                }
                                Core.flashMessage('Late fee schedule was deleted', 'success');
                            },
                            function (result) {
                                $scope.deleting = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        };

        $scope.assign = function (schedule) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'settings/views/mass-assign-fee.html',
                controller: 'MassAssignFeeController',
                backdrop: 'static',
                keyboard: false,
                size: 'sm',
                resolve: {
                    schedule: function () {
                        return schedule;
                    },
                },
            });

            modalInstance.result.then(
                function (n) {
                    LeavePageWarning.unblock();
                    schedule.num_customers += n;

                    Core.flashMessage(
                        'the fee schedule has been assigned to ' + n + ' customer' + (n != 1 ? 's' : ''),
                        'success',
                    );
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        //
        // Initialization
        //

        Core.setTitle('Late Fee Schedules');
        load();

        function load() {
            $scope.loading = true;
            LateFeeSchedule.findAll(
                {
                    include: 'num_customers',
                    paginate: 'none',
                },
                function (schedules) {
                    $scope.schedules = schedules;
                    $scope.loading = false;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                    $scope.loading = false;
                },
            );
        }
    }
})();
