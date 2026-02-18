(function () {
    'use strict';

    angular.module('app.settings').controller('ChasingLegacySettingsController', ChasingLegacySettingsController);

    ChasingLegacySettingsController.$inject = [
        '$scope',
        'Company',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
        'Settings',
    ];

    function ChasingLegacySettingsController($scope, Company, LeavePageWarning, selectedCompany, Core, Settings) {
        $scope.editingStep = {};
        $scope.chaseSchedule = [];
        $scope.hadChasing = false;

        let typeOrder = ['issued', 'before_due_date', 'on_due_date', 'after_due_date', 'repeat'];

        $scope.toggleChasing = function () {
            LeavePageWarning.block();
        };

        $scope.addStep = function ($event) {
            $scope.chaseSchedule.push({
                type: 'after_due_date',
                action: 'email',
                value: '',
            });

            LeavePageWarning.block();

            $scope.editStep($scope.chaseSchedule.length - 1, $event);
        };

        $scope.editStep = function (i, $event) {
            $event.stopPropagation();
            if ($scope.editingStep[i]) {
                return;
            }

            LeavePageWarning.block();
            $scope.editingStep[i] = true;
        };

        $scope.doneEditingStep = function (i, $event) {
            $event.stopPropagation();
            let step = $scope.chaseSchedule[i];
            if (
                (step.type === 'before_due_date' || step.type === 'after_due_date' || step.type === 'repeat') &&
                !step.value
            ) {
                return;
            }

            $scope.editingStep[i] = false;
            order();
        };

        $scope.deleteStep = function (i, $event) {
            $event.stopPropagation();
            $scope.chaseSchedule.splice(i, 1);
        };

        $scope.save = function () {
            $scope.saving = true;
            $scope.error = null;

            order();

            let params = {
                allow_chasing: $scope.allowChasing,
                chase_schedule: encodeSchedule($scope.chaseSchedule),
            };

            // enable chasing by default for new invoices if
            // they are just turning on chasing for the first time.
            // conversely, disable chasing by default for new
            // invoices if disabling chasing
            if (!$scope.hadChasing && $scope.allowChasing) {
                params.chase_new_invoices = true;
            } else if (!$scope.allowChasing) {
                params.chase_new_invoices = false;
            }

            Settings.editAccountsReceivable(
                params,
                function (settings) {
                    $scope.saving = false;
                    parseSchedule(settings.chase_schedule);

                    Core.flashMessage('Your chasing settings have been updated.', 'success');

                    $scope.chaseSettingsForm.$setPristine();

                    LeavePageWarning.unblock();
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        };

        Core.setTitle('Chasing (Legacy)');
        loadSettings();

        function loadSettings() {
            $scope.loading = true;

            Settings.accountsReceivable(
                function (settings) {
                    $scope.loading = false;
                    $scope.allowChasing = settings.allow_chasing;
                    parseSchedule(settings.chase_schedule);
                    $scope.hadChasing = settings.allow_chasing && settings.chase_schedule.length > 0;
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        function parseSchedule(schedule) {
            $scope.chaseSchedule = [];
            angular.forEach(schedule, function (el) {
                let step = {
                    action: 'email',
                };

                // parse the new format
                if (typeof el === 'object') {
                    step.action = el.action;
                    el = el.step;
                }

                if ((el + '').substring(0, 1) === '~') {
                    step.type = 'repeat';
                    step.value = parseInt(el.substring(1));
                } else if (el === 'issued') {
                    step.type = 'issued';
                } else if (parseInt(el) < 0) {
                    step.type = 'before_due_date';
                    step.value = Math.abs(parseInt(el));
                } else if (parseInt(el) === 0) {
                    step.type = 'on_due_date';
                    step.value = 0;
                } else if (parseInt(el) > 0) {
                    step.type = 'after_due_date';
                    step.value = parseInt(el);
                }

                $scope.chaseSchedule.push(step);
            });

            order();
        }

        function encodeSchedule(schedule) {
            let encoded = [];
            angular.forEach(schedule, function (step) {
                if (step.type === 'issued') {
                    encoded.push({
                        step: 'issued',
                        action: step.action,
                    });
                } else if (step.type === 'before_due_date' && step.value !== '') {
                    encoded.push({
                        step: step.value * -1,
                        action: step.action,
                    });
                } else if (step.type === 'on_due_date') {
                    encoded.push({
                        step: 0,
                        action: step.action,
                    });
                } else if (step.type === 'after_due_date' && step.value !== '') {
                    encoded.push({
                        step: step.value,
                        action: step.action,
                    });
                } else if (step.type === 'repeat' && step.value !== '') {
                    encoded.push({
                        step: '~' + step.value,
                        action: step.action,
                    });
                }
            });

            return uniq(encoded);
        }

        function order() {
            $scope.chaseSchedule
                .sort(function (a, b) {
                    if (a.type !== b.type) {
                        return typeOrder.indexOf(a.type) < typeOrder.indexOf(b.type) ? 1 : -1;
                    }

                    if (a.value === b.value) {
                        return 0;
                    }

                    if (a.type === 'before_due_date') {
                        return a.value > b.value ? 1 : -1;
                    }

                    if (a.type === 'after_due_date') {
                        return a.value > b.value ? -1 : 1;
                    }
                })
                .reverse();

            angular.forEach($scope.chaseSchedule, function (step) {
                step.description = stepDescription(step);
            });
        }

        function stepDescription(step) {
            let days = step.value + (step.value !== 1 ? ' days' : ' day');

            if (step.action === 'email') {
                if (step.type === 'issued') {
                    return 'Send invoice on the issue date';
                } else if (step.type === 'before_due_date') {
                    return 'Send a reminder ' + days + ' before the due date';
                } else if (step.type === 'on_due_date') {
                    return 'Send a reminder on the due date';
                } else if (step.type === 'after_due_date' && step.value !== '') {
                    return 'Send a past due reminder ' + days + ' after the due date';
                } else if (step.type === 'repeat' && step.value !== '') {
                    return 'Send a reminder every ' + days;
                }
            } else if (step.action === 'flag') {
                if (step.type === 'issued') {
                    return 'Flag invoice on the issue date';
                } else if (step.type === 'before_due_date') {
                    return 'Flag invoice ' + days + ' before the due date';
                } else if (step.type === 'on_due_date') {
                    return 'Flag invoice on the due date';
                } else if (step.type === 'after_due_date' && step.value !== '') {
                    return 'Flag invoice ' + days + ' after the due date';
                } else if (step.type === 'repeat' && step.value !== '') {
                    return 'Flag invoice every ' + days;
                }
            }

            return '';
        }

        function uniq(a) {
            let seen = {};
            return a.filter(function (item) {
                return seen.hasOwnProperty(item.step) ? false : (seen[item.step] = true);
            });
        }
    }
})();
