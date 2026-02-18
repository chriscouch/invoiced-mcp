/* globals moment */
(function () {
    'use strict';

    angular.module('app.automations').controller('AddAutomationStepController', AddAutomationStepController);

    AddAutomationStepController.$inject = [
        '$scope',
        '$modalInstance',
        '$filter',
        'InvoicedConfig',
        'Core',
        'Member',
        'CurrentUser',
        'AutomationBuilder',
        'Slack',
        'workflow',
        'step',
    ];

    function AddAutomationStepController(
        $scope,
        $modalInstance,
        $filter,
        InvoicedConfig,
        Core,
        Member,
        CurrentUser,
        AutomationBuilder,
        Slack,
        workflow,
        step,
    ) {
        $scope.loading = true;
        const escapeHtml = $filter('escapeHtml');
        $scope.eventTypes = InvoicedConfig.eventTypes;
        $scope.workflow = workflow;
        $scope.membersLoaded = false;
        $scope.workflowObjectName = workflow.object_type;
        if (step) {
            $scope.step = angular.copy(step);
            selectAction(step.action_type);
        } else {
            $scope.step = {
                action_type: null,
                settings: {},
            };
        }

        $scope.dateRangePeriods = {
            weeks: 'Past week',
            days: 'Past 30 days',
            years: 'Past year',
            this_month: 'This month-to-date',
            last_month: 'Last month',
            this_quarter: 'This quarter-to-date',
            last_quarter: 'Last quarter',
            this_year: 'This year-to-date',
            last_year: 'Last year',
        };

        AutomationBuilder.getObjectTypes(function (objectTypes) {
            const objectType = objectTypes.find(item => item.object === $scope.workflow.object_type);
            $scope.actionTypes = objectType.actions;
            $scope.loading = false;
        });

        $scope.selectAction = selectAction;
        $scope.changeObject = changeObject;
        $scope.addField = addField;
        $scope.removeField = removeField;
        $scope.getTypes = getTypes;
        $scope.addFieldVariable = addFieldVariable;
        $scope.addSettingVariable = addSettingVariable;
        $scope.copyMe = copyMe;
        $scope.addEmail = addEmail;
        $scope.shortcut = shortcut;

        $scope.add = function (step) {
            if (
                typeof step.settings.value !== 'undefined' &&
                step.settings.value &&
                typeof step.settings.value.getMonth === 'function'
            ) {
                const value = moment(step.settings.value);
                if (value.isValid()) {
                    step.settings.value = value.format('YYYY-MM-DD');
                }
            }
            $modalInstance.close(step);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        $scope.autocompleteOptions = {
            multiple: true,
            simple_tags: true,
            tags: [],
            maximumSelectionSize: 10,
            minimumInputLength: 3,
            maximumInputLength: 50,
            tokenSeparators: [',', ' ', ';'],
            width: '100%',
            placeholder: 'Enter email address',
            formatNoMatches: function () {
                return 'No match found';
            },
            formatResult: function (item) {
                return '<span class="create">Add <span>' + escapeHtml(item.text) + '</span> as email</span>';
            },
            createSearchChoice: function (term) {
                const atIndex = term.indexOf('@');
                if (
                    (atIndex === -1 || atIndex === term.length - 1) &&
                    (term.indexOf('{{') !== 0 || term.indexOf('}}') !== term.length - 2)
                ) {
                    return null;
                }

                return {
                    text: term,
                    id: term,
                };
            },
            formatInputTooShort: false,
            dropdownCssClass: 'email-recipient-dropdown',
            createSearchChoicePosition: 'top',
            formatSelection: function (term) {
                return escapeHtml(term.text);
            },
        };

        $scope.selectRecipients = {
            data: [],
            placeholder: 'Select recipients',
            width: '100%',
        };

        function copyMe() {
            $scope.step.settings.bcc.push(CurrentUser.profile.email);
        }
        function addEmail(value, object) {
            object.push('{{' + value + '}}');
            $scope.variable = '';
        }

        function addVariable(variable, type) {
            let el = $('#' + type);
            let pos = el.caret();

            // inject at position in template
            $scope.variable = null;
            return spliceSlice(el.val(), pos, 0, '{{' + variable + '}}');
        }

        function addSettingVariable(variable, type, property) {
            if (typeof property === 'undefined') {
                property = type;
            }
            // inject at position in template
            $scope.step.settings[property] = addVariable(variable, type);
        }

        function addFieldVariable(variable, type, property) {
            $scope.step.settings.fields[property].value = addVariable(variable, type);
        }

        function spliceSlice(str, index, count, add) {
            return str.slice(0, index) + (add || '') + str.slice(index + count);
        }

        function getTypes(destinationObjectType, sourceObjectType, selectedProperty, writable) {
            if (!$scope.properties) {
                return [];
            }
            const property = $scope.properties[sourceObjectType].find(property => property.id === selectedProperty);

            return $scope.properties[destinationObjectType].filter(function (item) {
                if (item.type !== property.type) {
                    return false;
                }
                if (writable && !item.writable) {
                    return false;
                }

                if (destinationObjectType === sourceObjectType && property.id === item.id) {
                    return false;
                }

                if (item.join) {
                    return property.join && item.id === property.id;
                }

                return true;
            });
        }

        function buildFields(objectTypes) {
            $scope.properties = {};
            $scope.variables = [];
            $scope.email_variables = [];
            angular.forEach(objectTypes, function (objectType) {
                $scope.properties[objectType.object] = objectType.properties;
                angular.forEach(objectType.properties, function (property) {
                    $scope.variables.push(objectType.object + '.' + property.id);
                    if (
                        property.type === 'string' &&
                        (property.id.indexOf('email') !== -1 || property.id.indexOf('metadata') !== -1)
                    ) {
                        $scope.email_variables.push(objectType.object + '.' + property.id);
                    }
                });
            });
        }

        function changeObject() {
            const subject = $scope.subjectActions[$scope.step.settings.object_type];
            $scope.step.settings.fields = [];
            for (const i in subject) {
                addField(i, subject[i]);
            }
        }

        function addField(key, value) {
            const field =
                typeof key === 'undefined'
                    ? {
                          name: null,
                          value: null,
                          required: false,
                      }
                    : {
                          name: key,
                          value: value ? '{{' + $scope.workflow.object_type + '.' + value + '}}' : null,
                          required: true,
                      };
            $scope.step.settings.fields.push(field);
        }

        function removeField(index) {
            $scope.step.settings.fields.splice(index, 1);
        }

        function setDefaults(actionType) {
            if (!$scope.step.settings.object_type) {
                if (
                    actionType === 'Condition' ||
                    actionType === 'ModifyPropertyValue' ||
                    actionType === 'CopyPropertyValue' ||
                    actionType === 'ClearPropertyValue'
                ) {
                    $scope.step.settings = {
                        name: null,
                        value: null,
                        object_type: $scope.workflow.object_type,
                    };
                }
                if (actionType === 'SendEmail' && typeof $scope.step.settings.to === 'undefined') {
                    $scope.step.settings.to = [];
                    $scope.step.settings.cc = [];
                    $scope.step.settings.bcc = [];
                }
            }
        }

        function selectAction(actionType) {
            $scope.step.action_type = actionType;
            $scope.objectTypes = [];
            if (actionType === 'DeleteObject') {
                if (!$scope.step.settings.object_type) {
                    $scope.step.settings.object_type = $scope.workflow.object_type;
                }
                return;
            }

            $scope.loading = true;
            AutomationBuilder.getFieldsForAction(
                $scope.workflow.object_type,
                actionType,
                function (result, filteredSubjectTypes) {
                    angular.forEach(result, function (objectType) {
                        $scope.objectTypes.push({ id: objectType.object, name: objectType.name });
                        if (objectType.object === $scope.workflow.object_type) {
                            $scope.workflowObjectName = objectType.name;
                        }
                    });
                    buildFields(result);
                    setDefaults(actionType);
                    if (actionType === 'PostToSlack') {
                        Slack.channels(
                            function (channels) {
                                $scope.channels = channels;
                                //this fixes the channel specified as a name
                                if (step.settings.channel.indexOf('#') === 0) {
                                    for (let i = 0; i < channels.length; i++) {
                                        if ('#' + channels[i].name === step.settings.channel) {
                                            $scope.step.settings.channel = channels[i].id;
                                            break;
                                        }
                                    }
                                }

                                $scope.loading = false;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                                $scope.loading = false;
                            },
                        );
                    }
                    if (actionType === 'SendInternalNotification') {
                        Member.all(
                            function (members) {
                                const settingsMembers = [];
                                angular.forEach(members, function (member) {
                                    const memberData = {
                                        id: member.id,
                                        text: formatMemberName(member),
                                    };
                                    if (
                                        !$scope.membersLoaded &&
                                        $scope.step.settings.members &&
                                        $scope.step.settings.members.indexOf(member.id) !== -1
                                    ) {
                                        settingsMembers.push(memberData);
                                    }
                                    $scope.selectRecipients.data.push(memberData);
                                });
                                $scope.membersLoaded = true;
                                if (
                                    $scope.step.settings.members &&
                                    $scope.step.settings.members.length > 0 &&
                                    typeof $scope.step.settings.members[0] !== 'object'
                                ) {
                                    $scope.step.settings.members = settingsMembers;
                                }
                                $scope.loading = false;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                                $scope.loading = false;
                            },
                        );
                    } else {
                        $scope.loading = false;
                    }
                    if (actionType === 'CreateObject') {
                        $scope.subjectActions = result.find(
                            entry => entry.object === $scope.workflow.object_type,
                        ).subjectActions[actionType];
                        $scope.subjectTypes = [];
                        angular.forEach(filteredSubjectTypes, function (objectType) {
                            $scope.subjectTypes.push({ id: objectType.object, name: objectType.name });
                            if (!$scope.properties[objectType.object]) {
                                $scope.properties[objectType.object] = objectType.properties;
                            }
                        });
                    }
                    if (actionType === 'SendDocument') {
                        if ($scope.workflow.object_type === 'customer') {
                            if (!$scope.step.settings.type) {
                                $scope.step.settings.type = 'open_item';
                                $scope.step.settings.openItemMode = 'open';
                                $scope.step.settings.period = 'last_month';
                            }
                        }
                    }
                },
            );
        }

        function formatMemberName(member) {
            return member.user.first_name + ' ' + member.user.last_name;
        }

        function shortcut(variable) {
            $scope.step.settings.period = variable;
        }
    }
})();
