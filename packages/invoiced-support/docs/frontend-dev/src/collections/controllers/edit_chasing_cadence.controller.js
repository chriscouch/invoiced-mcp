/* globals vex */
(function () {
    'use strict';

    angular.module('app.collections').controller('EditChasingCadenceController', EditChasingCadenceController);

    EditChasingCadenceController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$modal',
        'Company',
        'LeavePageWarning',
        'selectedCompany',
        'Core',
        'Customer',
        'ChasingCadence',
        'SmsTemplate',
        'Member',
        'Feature',
    ];

    function EditChasingCadenceController(
        $scope,
        $state,
        $stateParams,
        $modal,
        Company,
        LeavePageWarning,
        selectedCompany,
        Core,
        Customer,
        ChasingCadence,
        SmsTemplate,
        Member,
        Feature,
    ) {
        $scope.hasFeature = Feature.hasFeature('smart_chasing');
        $scope.company = angular.copy(selectedCompany);
        $scope.tab = 'basic';
        $scope.loading = 0;
        $scope.conditionEditor = 'advanced';
        $scope.assignmentConditions = [];
        $scope.contactRoles = [];

        let typeOrder = ['age', 'past_due_age'];

        $scope.customerProperties = [
            {
                id: 'name',
                name: 'Name',
            },
            {
                id: 'number',
                name: 'Account #',
            },
            {
                id: 'email',
                name: 'Email Address',
            },
            {
                id: 'type',
                name: 'Type',
            },
            {
                id: 'autopay',
                name: 'AutoPay',
            },
            {
                id: 'payment_terms',
                name: 'Payment Terms',
            },
            {
                id: 'attention_to',
                name: 'Attention To',
            },
            {
                id: 'address1',
                name: 'Address Line 1',
            },
            {
                id: 'address2',
                name: 'Address Line 2',
            },
            {
                id: 'city',
                name: 'City',
            },
            {
                id: 'state',
                name: 'State',
            },
            {
                id: 'postal_code',
                name: 'Postal Code',
            },
            {
                id: 'country',
                name: 'Country',
            },
            {
                id: 'tax_id',
                name: 'Tax ID',
            },
            {
                id: 'phone',
                name: 'Phone',
            },
            {
                id: 'notes',
                name: 'Notes',
            },
            {
                id: 'language',
                name: 'Language',
            },
        ];

        $scope.addStep = function () {
            if (!$scope.canEditSteps) {
                return;
            }

            $scope.cadence.steps.push({
                type: 'age',
                action: 'email',
                value: '',
                for: 'open',
                assigned_user_id: -1,
                editing: true,
            });
        };

        $scope.editStep = function (step) {
            step.editing = true;
        };

        $scope.canFinishEditingStep = function (step) {
            if (!step.name) {
                return false;
            }

            if (step.value === null || step.value === '' || typeof step.value === 'undefined') {
                return false;
            }

            if (step.action === 'email' && !step.email_template_id) {
                return false;
            }

            if (step.action === 'sms' && !step.sms_template_id) {
                return false;
            }

            return true;
        };

        $scope.doneEditingStep = function (step) {
            if (!$scope.canFinishEditingStep(step)) {
                return;
            }

            step.editing = false;
            order();
        };

        $scope.duplicateStep = function (step) {
            if (!$scope.canEditSteps) {
                return;
            }

            step = angular.copy(step);

            if (typeof step.$$hashKey !== 'undefined') {
                delete step.$$hashKey;
            }

            if (typeof step.id !== 'undefined') {
                delete step.id;
            }

            $scope.cadence.steps.push(step);
        };

        $scope.deleteStep = function (i) {
            if (!$scope.canEditSteps) {
                return;
            }

            vex.dialog.confirm({
                message: 'Are you sure you want to delete this step?',
                callback: function (result) {
                    if (result) {
                        $scope.$apply(function () {
                            $scope.cadence.steps.splice(i, 1);
                        });
                    }
                },
            });
        };

        $scope.newSmsTemplate = function (step) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'sending/views/edit-sms-template.html',
                controller: 'EditSmsTemplateController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    template: function () {
                        return {
                            name: step.name,
                            message: '{{contact_name}}, your account balance of {{account_balance}} is now due {{url}}',
                        };
                    },
                },
            });

            modalInstance.result.then(
                function (template) {
                    $scope.smsTemplates.push(template);
                    step.sms_template_id = template.id;
                    LeavePageWarning.unblock();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.addCondition = function () {
            $scope.assignmentConditions.push({
                property: '',
                operator: '=',
                value: '',
            });
        };

        $scope.save = function (cadence) {
            $scope.saving = true;
            $scope.error = null;

            order();

            let params = {
                name: cadence.name,
                time_of_day: parseInt(cadence.time_of_day),
                frequency: cadence.frequency,
                min_balance: $scope.hasMinBalance ? cadence.min_balance : null,
                assignment_mode: cadence.assignment_mode,
            };

            params.run_date = null;
            params.run_days = null;
            if (cadence.frequency === 'day_of_month') {
                params.run_date = cadence.run_date;
            } else if (cadence.frequency === 'day_of_week') {
                params.run_days = cadence.run_days
                    .map(function (item) {
                        return item.id;
                    })
                    .join(',');
            }

            params.steps = encodeSchedule($scope.cadence.steps);

            if (cadence.assignment_mode == 'conditions') {
                params.assignment_conditions = cadence.assignment_conditions;
            }

            if (cadence.id) {
                ChasingCadence.edit(
                    {
                        id: cadence.id,
                    },
                    params,
                    function () {
                        $scope.saving = false;

                        Core.flashMessage('Your chasing cadence has been updated.', 'success');

                        LeavePageWarning.unblock();
                        $state.go('manage.settings.chasing.customers');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                ChasingCadence.create(
                    params,
                    function () {
                        $scope.saving = false;

                        Core.flashMessage('Your chasing cadence has been created.', 'success');

                        LeavePageWarning.unblock();
                        $state.go('manage.settings.chasing.customers');
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.stepDescription = stepDescription;

        LeavePageWarning.watchForm($scope, 'cadenceForm');

        if ($stateParams.id) {
            Core.setTitle('Edit Chasing Cadence');
            loadCadence($stateParams.id);
            $scope.isExisting = true;
        } else {
            Core.setTitle('New Chasing Cadence');
            $scope.cadence = {
                name: '',
                time_of_day: 7,
                frequency: 'daily',
                steps: [],
                assignment_mode: 'none',
            };
            $scope.canEditSteps = true;
            $scope.addStep();
        }

        loadContactRoles();
        loadSmsTemplates();
        loadMembers();

        $scope.days = {
            data: [
                { id: 1, text: 'Monday' },
                { id: 2, text: 'Tuesday' },
                { id: 3, text: 'Wednesday' },
                { id: 4, text: 'Thursday' },
                { id: 5, text: 'Friday' },
                { id: 6, text: 'Saturday' },
                { id: 7, text: 'Sunday' },
            ],
            width: '100%',
        };

        $scope.monthMapping = [];
        for (let i = 1; i <= 31; i++) {
            $scope.monthMapping.push({
                id: i,
                name: ordinal_suffix_of(i),
            });
        }

        function loadCadence(id) {
            $scope.loading++;

            ChasingCadence.find(
                {
                    id: id,
                    include: 'num_customers',
                },
                function (cadence) {
                    $scope.loading--;
                    $scope.hasMinBalance = cadence.min_balance > 0;
                    $scope.cadence = cadence;
                    parseSchedule(cadence);
                    order();

                    if ($state.current.name === 'manage.collections.duplicate_chasing_cadence') {
                        delete cadence.id;
                        angular.forEach(cadence.steps, function (step) {
                            delete step.id;
                        });
                        Core.setTitle('New Chasing Cadence');
                        $scope.canEditSteps = true;
                        $scope.isExisting = false;
                    } else {
                        $scope.canEditSteps = !$scope.isExisting || $scope.cadence.num_customers === 0;
                    }
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        function loadContactRoles() {
            $scope.loading++;
            Customer.contactRoles(
                function (roles) {
                    $scope.contactRoles = roles;
                    $scope.loading--;
                },
                function (result) {
                    $scope.error = result.data;
                    $scope.loading--;
                },
            );
        }

        function loadSmsTemplates() {
            $scope.loading++;

            $scope.smsTemplates = [];
            SmsTemplate.findAll(
                { paginate: 'none' },
                function (templates) {
                    $scope.loading--;
                    $scope.smsTemplates = templates;
                },
                function (result) {
                    $scope.loading--;
                    $scope.error = result.data;
                },
            );
        }

        function loadMembers() {
            $scope.loading++;

            Member.all(
                function (members) {
                    $scope.loading--;

                    $scope.users = [
                        {
                            id: -1,
                            name: 'Account Owner',
                        },
                    ];

                    angular.forEach(members, function (member) {
                        $scope.users.push({
                            id: member.user.id,
                            name: member.user.first_name + ' ' + member.user.last_name,
                        });
                    });
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function parseSchedule(cadence) {
            let days = [];
            if (cadence.frequency === 'day_of_week') {
                if (cadence.run_days) {
                    days = cadence.run_days.split(',');
                } else if (cadence.run_date) {
                    days = [cadence.run_date];
                }
            }
            days = days.map(function (d) {
                return parseInt(d);
            });
            cadence.run_days = [];
            angular.forEach($scope.days.data, function (day) {
                if (days.indexOf(day.id) !== -1) {
                    cadence.run_days.push(day);
                }
            });

            angular.forEach(cadence.steps, function (step) {
                step.editing = false;
                let parts = step.schedule.split(':');
                step.type = parts[0];
                if (parts.length > 1) {
                    let args = parts[1].split(',');
                    step.value = parseInt(args[0]);
                    step.for = args.length > 1 ? args[1] : 'open';
                } else {
                    step.value = '';
                    step.for = 'open';
                }

                if ((step.action === 'escalate' || step.action === 'phone') && !step.assigned_user_id) {
                    step.assigned_user_id = -1;
                }
            });

            order();
        }

        function encodeSchedule(steps) {
            let encoded = [];
            angular.forEach(steps, function (step) {
                step = angular.copy(step);

                if (step.action === 'escalate' || step.action === 'phone') {
                    if (step.assigned_user_id <= 0) {
                        step.assigned_user_id = null;
                    }
                } else {
                    step.assigned_user_id = null;
                }

                step.schedule = step.type + ':' + step.value;

                delete step.type;
                delete step.value;
                delete step.created_at;
                delete step.$$hashKey;
                delete step.editing;

                encoded.push(step);
            });

            return encoded;
        }

        function order() {
            $scope.cadence.steps
                .sort(function (a, b) {
                    if (a.type != b.type) {
                        return typeOrder.indexOf(a.type) < typeOrder.indexOf(b.type) ? 1 : -1;
                    }

                    if (a.value == b.value) {
                        return 0;
                    }

                    if (a.type == 'age') {
                        return a.value < b.value ? 1 : -1;
                    }

                    if (a.type == 'past_due_age') {
                        return a.value < b.value ? 1 : -1;
                    }
                })
                .reverse();
        }

        // Found on: http://stackoverflow.com/questions/13627308/add-st-nd-rd-and-th-ordinal-suffix-to-a-number#13627586
        function ordinal_suffix_of(i) {
            let j = i % 10,
                k = i % 100;
            if (j == 1 && k != 11) {
                return i + 'st';
            }
            if (j == 2 && k != 12) {
                return i + 'nd';
            }
            if (j == 3 && k != 13) {
                return i + 'rd';
            }
            return i + 'th';
        }

        function stepDescription(type, value) {
            if (type === 'age') {
                return 'Age: ' + value + ' day' + (value != 1 ? 's' : '');
            } else if (type === 'past_due_age') {
                return 'Past Due Age: ' + value + ' day' + (value != 1 ? 's' : '');
            }
        }
    }
})();
