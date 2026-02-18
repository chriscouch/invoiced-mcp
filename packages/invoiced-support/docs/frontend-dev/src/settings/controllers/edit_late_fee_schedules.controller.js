/* globals moment */
(function () {
    'use strict';

    angular.module('app.settings').controller('EditLateFeeSchedulesController', EditLateFeeSchedulesController);

    EditLateFeeSchedulesController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'LateFeeSchedule',
        'schedule',
        'DatePickerService',
    ];

    function EditLateFeeSchedulesController(
        $scope,
        $modalInstance,
        selectedCompany,
        LateFeeSchedule,
        schedule,
        DatePickerService,
    ) {
        if (schedule) {
            $scope.schedule = angular.copy(schedule);
            $scope.schedule.start_date = moment(schedule.start_date).toDate();
        } else {
            $scope.schedule = {
                enabled: true,
                is_percent: true,
                start_date: new Date(),
            };
        }

        $scope.currency = selectedCompany.currency;
        $scope.hasRecurringLateFee = $scope.schedule.recurring_days > 0;
        $scope.dateOptions = DatePickerService.getOptions();
        $scope.dpOpened = false;

        $scope.save = function (schedule) {
            $scope.saving = true;

            let params = {
                name: schedule.name,
                start_date: moment(schedule.start_date).format('YYYY-MM-DD'),
                grace_period: schedule.grace_period,
                amount: schedule.amount,
                is_percent: schedule.is_percent,
                recurring_days: $scope.hasRecurringLateFee ? schedule.recurring_days : 0,
                enabled: schedule.enabled,
            };

            if (schedule.id !== undefined) {
                LateFeeSchedule.edit(
                    {
                        id: schedule.id,
                    },
                    params,
                    function (data) {
                        $scope.saving = false;
                        $modalInstance.close(data);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            } else {
                LateFeeSchedule.create(
                    params,
                    function (data) {
                        data.num_customers = 0;
                        $scope.saving = false;
                        $modalInstance.close(data);
                    },
                    function (result) {
                        $scope.error = result.data;
                        $scope.saving = false;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };
    }
})();
