/* globals moment */
(function () {
    'use strict';

    angular.module('app.automations').controller('AddAutomationTriggerController', AddAutomationTriggerController);

    AddAutomationTriggerController.$inject = [
        '$scope',
        '$modalInstance',
        '$translate',
        '$timeout',
        '$window',
        'InvoicedConfig',
        'AutomationBuilder',
        'DatePickerService',
        'workflow',
        'trigger',
    ];

    function AddAutomationTriggerController(
        $scope,
        $modalInstance,
        $translate,
        $timeout,
        $window,
        InvoicedConfig,
        AutomationBuilder,
        DatePickerService,
        workflow,
        trigger,
    ) {
        $scope.workflow = workflow;
        $scope.trigger = trigger || {
            trigger_type: null,
            event_type: null,
        };
        $scope.dpOpened1 = {};
        $scope.dpOpened2 = {};
        $scope.dateOptions = DatePickerService.getOptions();

        $scope.openDatepicker = function ($event, name) {
            $event.stopPropagation();
            $scope[name] = true;
            // this is needed to ensure the datepicker
            // can be opened again
            $timeout(function () {
                $scope[name] = false;
            });
        };
        $scope.freqOptions = [
            {
                name: $translate.instant('automations.freq_options.monthly'),
                id: $window.rrule.RRule.MONTHLY,
            },
            {
                name: $translate.instant('automations.freq_options.weekly'),
                id: $window.rrule.RRule.WEEKLY,
            },
            {
                name: $translate.instant('automations.freq_options.daily'),
                id: $window.rrule.RRule.DAILY,
            },
            {
                name: $translate.instant('automations.freq_options.hourly'),
                id: $window.rrule.RRule.HOURLY,
            },
        ];

        $scope.weekDayOptions = {
            data: [
                {
                    text: $translate.instant('automations.freq_options.monday'),
                    id: $window.rrule.RRule.MO.weekday,
                },
                {
                    text: $translate.instant('automations.freq_options.tuesday'),
                    id: $window.rrule.RRule.TU.weekday,
                },
                {
                    text: $translate.instant('automations.freq_options.wednesday'),
                    id: $window.rrule.RRule.WE.weekday,
                },
                {
                    text: $translate.instant('automations.freq_options.thursday'),
                    id: $window.rrule.RRule.TH.weekday,
                },
                {
                    text: $translate.instant('automations.freq_options.friday'),
                    id: $window.rrule.RRule.FR.weekday,
                },
                {
                    text: $translate.instant('automations.freq_options.saturday'),
                    id: $window.rrule.RRule.SA.weekday,
                },
                {
                    text: $translate.instant('automations.freq_options.sunday'),
                    id: $window.rrule.RRule.SU.weekday,
                },
            ],
            placeholder: 'Days of the week',
            width: '100%',
        };

        $scope.months = {
            data: moment.months().map(month => ({
                text: month,
                id: moment().month(month).format('M'),
            })),
            placeholder: 'Months',
            width: '100%',
        };

        $scope.example = [];

        $scope.schedule = {
            freq: $window.rrule.RRule.DAILY,
            dtstart: moment().startOf('hour').toDate(),
            interval: 1,
            bymonth: null,
            byweekday: null,
            bymonthday: null,
            byhour: moment().add(1, 'hour').hour().toString(),
        };

        if ($scope.trigger.r_rule) {
            $scope.schedule = new $window.rrule.rrulestr($scope.trigger.r_rule).options;
            //the bug with rrule parsing from string
            if ($scope.trigger.r_rule.indexOf('BYMONTHDAY') === -1) {
                $scope.schedule.bymonthday = null;
            }
            //translate time to proper offset
            if ($scope.schedule.byhour) {
                $scope.schedule.byhour = $scope.schedule.byhour.map(hour => hour + moment.parseZone().utcOffset() / 60);
            }
        }

        $scope.showSection = function (section) {
            return $scope.schedule.freq === $window.rrule.RRule[section];
        };

        $scope.updateFrequency = function () {
            if ($scope.schedule.freq === $window.rrule.RRule.HOURLY) {
                $scope.schedule.byhour = null;
            }

            $scope.updateSchedule();
        };

        $scope.updateSchedule = function () {
            if ($scope.schedule.freq === $window.rrule.RRule.HOURLY) {
                $scope.schedule.dtstart = moment().add(1, 'hour').startOf('hour').toDate();
            } else {
                $scope.schedule.dtstart = moment().add(1, 'day').startOf('day').toDate();
            }
            if ($scope.schedule.byhour && $scope.schedule.freq === $window.rrule.RRule.HOURLY) {
                $scope.schedule.freq = $window.rrule.RRule.DAILY;
            }
            if (
                $scope.schedule.freq !== $window.rrule.RRule.WEEKLY &&
                $scope.schedule.freq !== $window.rrule.RRule.DAILY &&
                $scope.schedule.freq !== $window.rrule.RRule.HOURLY
            ) {
                $scope.schedule.byweekday = null;
            } else if ($scope.schedule.byweekday) {
                $scope.schedule.byweekday = $scope.schedule.byweekday.map(item =>
                    typeof item === 'object' ? item : $scope.weekDayOptions.data.find(item2 => item2.id == item),
                );
            }
            if ($scope.schedule.freq !== $window.rrule.RRule.MONTHLY) {
                $scope.schedule.bymonth = null;
                $scope.schedule.bymonthday = null;
            } else if ($scope.schedule.bymonth) {
                $scope.schedule.bymonth = $scope.schedule.bymonth.map(item =>
                    typeof item === 'object' ? item : $scope.months.data.find(item2 => item2.id == item),
                );
            }

            $scope.schedule.byminute = 0;
            $scope.schedule.bysecond = 0;

            //normalize to nulls
            $scope.schedule = angular.forEach($scope.schedule, function (item, key) {
                if (!item) {
                    $scope.schedule[key] = null;
                }
            });

            const freq = normalizeSchedule(angular.copy($scope.schedule), (moment.parseZone().utcOffset() * -1) / 60);

            //show examples
            $scope.examples = new $window.rrule.RRule(freq).all(function (date, i) {
                return i < 11;
            });
            //this one goes to save
            delete freq.dtstart;
            const rule = new $window.rrule.RRule(freq);
            $scope.rule = rule.toString();
        };

        $scope.updateSchedule();

        $scope.allowedTriggers = [];

        $scope.eventTypes = [];
        angular.forEach(InvoicedConfig.eventTypes, function (eventType) {
            if (eventType === 'invoice.payment_expected') {
                if (workflow.object_type === 'promise_to_pay') {
                    $scope.eventTypes.push({
                        name: $translate.instant('events.' + eventType),
                        id: eventType,
                    });
                }
                return;
            }
            // The allowed event types must start with the workflow object type.
            if (eventType.indexOf(workflow.object_type) === 0) {
                $scope.eventTypes.push({
                    name: $translate.instant('events.' + eventType),
                    id: eventType,
                });
            }
        });

        $scope.add = function (trigger) {
            if (trigger.trigger_type !== 'Event') {
                delete trigger.event_type;
            }

            $scope.updateSchedule();
            trigger.r_rule = $scope.rule;

            $modalInstance.close(trigger);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        load();

        function load() {
            AutomationBuilder.getObjectTypes(function (objectTypes) {
                angular.forEach(objectTypes, function (objectType) {
                    if (objectType.object === workflow.object_type) {
                        angular.forEach(objectType.triggers, function (trigger) {
                            $scope.allowedTriggers.push({
                                name: $translate.instant('automations.trigger_names.' + trigger),
                                id: trigger,
                            });
                        });
                    }
                });
            });
        }

        function normalizeSchedule(schedule, offset) {
            schedule.freq = parseInt($scope.schedule.freq);
            if (schedule.bymonth) {
                schedule.bymonth = schedule.bymonth.map(item => parseInt(item.id));
            }
            if (schedule.bymonthday) {
                if (typeof schedule.bymonthday === 'string') {
                    schedule.bymonthday = schedule.bymonthday.split(',');
                }
                schedule.bymonthday = schedule.bymonthday.map(item => parseInt(item));
            }
            if (schedule.byweekday) {
                schedule.byweekday = schedule.byweekday.map(item =>
                    typeof item === 'object' ? parseInt(item.id) : parseInt(item),
                );
            }
            if (schedule.byhour) {
                //fix fox incorrect dates for selected hours
                if (typeof schedule.byhour === 'string') {
                    schedule.byhour = schedule.byhour.split(',');
                }
                schedule.byhour = schedule.byhour.map(item => AutomationBuilder.fixOffset(item, offset));
            }

            return schedule;
        }
    }
})();
