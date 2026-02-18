(function () {
    'use strict';

    angular.module('app.sending').controller('EditEmailTemplateController', EditEmailTemplateController);

    EditEmailTemplateController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'EmailTemplate',
        'CustomField',
        'template',
        'InvoicedConfig',
        'Core',
        'options',
    ];

    function EditEmailTemplateController(
        $scope,
        $modalInstance,
        selectedCompany,
        EmailTemplate,
        CustomField,
        template,
        InvoicedConfig,
        Core,
        options,
    ) {
        $scope.company = selectedCompany;
        $scope.template = angular.copy(template);
        $scope.options = [];
        $scope.variables = [];
        $scope.canEditName = options.canEditName;
        $scope.canEditType = options.canEditType;

        let defaultEmailVariable = '-- Insert Variable';
        let allowedOptions = [];
        // These are the object types which support
        // custom fields in emails
        let customFieldObjects = ['customer', 'invoice', 'credit_note', 'estimate'];

        $scope.changeType = changeType;

        $scope.addVariable = function (variable, type) {
            if (variable === defaultEmailVariable) {
                return;
            }

            let el = $('#email-template-' + type);
            let pos = el.caret();

            // inject at position in template
            $scope.template[type] = spliceSlice(el.val(), pos, 0, variable);

            // reset the selection
            $scope.variable = defaultEmailVariable;
        };

        $scope.save = function (_template) {
            $scope.saving = true;

            let params = angular.copy(_template);

            // restrict the options saved to those allowed
            // by the email template
            let newOptions = {};
            angular.forEach(params.options, function (value, key) {
                if (allowedOptions.indexOf(key) !== -1) {
                    newOptions[key] = value;
                }
            });
            params.options = newOptions;

            // determine template engine
            params.template_engine = determineTemplateEngine(params);

            if (params.id) {
                delete params.id;

                if (params.subject === '' && params.body === '') {
                    params.template_engine = 'twig';
                }

                EmailTemplate.edit(
                    {
                        id: _template.id,
                    },
                    params,
                    function () {
                        $scope.error = null;
                        $scope.saving = false;

                        Core.flashMessage('Your email template has been updated.', 'success');

                        $modalInstance.close(_template);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                EmailTemplate.create(
                    params,
                    function (_template2) {
                        $scope.error = null;
                        $scope.saving = false;

                        Core.flashMessage('Your email template has been created.', 'success');

                        $modalInstance.close(_template2);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        changeType(template.type);

        function spliceSlice(str, index, count, add) {
            return str.slice(0, index) + (add || '') + str.slice(index + count);
        }

        function coerce(val, option) {
            if (option.boolean) {
                return !!val;
            } else if (option.number) {
                return parseInt(val);
            }

            return val;
        }

        function changeType(type) {
            let templateId = $scope.template.id;

            // determine the list of available variables
            if (typeof InvoicedConfig.templates.emailVariables[templateId] !== 'undefined') {
                $scope.variables = angular.copy(InvoicedConfig.templates.emailVariables[templateId]);
            } else if (typeof InvoicedConfig.templates.emailVariablesByType[type] !== 'undefined') {
                $scope.variables = angular.copy(InvoicedConfig.templates.emailVariablesByType[type]);
            } else {
                $scope.variables = [];
            }

            $scope.variables.splice(0, 0, defaultEmailVariable);
            $scope.variable = defaultEmailVariable;

            addCustomFieldVariables();

            // set up the options for this template
            if (typeof InvoicedConfig.emailTemplateOptionsByTemplate[templateId] !== 'undefined') {
                $scope.options = InvoicedConfig.emailTemplateOptionsByTemplate[templateId];
            } else if (typeof InvoicedConfig.emailTemplateOptionsByType[type] !== 'undefined') {
                $scope.options = InvoicedConfig.emailTemplateOptionsByType[type];
            } else {
                $scope.options = {};
            }

            // fill in any missing options with defaults
            // and perform type coercion for the UI
            allowedOptions = [];
            angular.forEach($scope.options, function (option) {
                let optionId = option.id;
                let value;
                if (typeof $scope.template.options[optionId] === 'undefined') {
                    value = option.default;
                } else {
                    value = $scope.template.options[optionId];
                }

                $scope.template.options[optionId] = coerce(value, option);
                allowedOptions.push(optionId);
            });
        }

        function addCustomFieldVariables() {
            CustomField.all(function (customFields) {
                angular.forEach(customFieldObjects, function (type) {
                    let variable = '{{' + type + '}}';
                    let index = $scope.variables.indexOf(variable);
                    if (index === -1) {
                        return;
                    }

                    $scope.variables.splice(index, 1);

                    angular.forEach(customFields, function (customField) {
                        if (customField.object === type) {
                            $scope.variables.push('{{' + type + '.metadata.' + customField.id + '}}');
                        }
                    });
                });

                $scope.variables = $scope.variables.sort(function (a, b) {
                    a = a.replace(/[{}]/g, '');
                    b = b.replace(/[{}]/g, '');
                    if (a < b) {
                        return -1;
                    }

                    if (a > b) {
                        return 1;
                    }

                    return 0;
                });
            });
        }
    }

    function determineTemplateEngine(template) {
        // Check if mustache elements are not used
        let notUsingMustache =
            template.body.indexOf('{{#') === -1 &&
            template.body.indexOf('{{/') === -1 &&
            template.body.indexOf('{{&') === -1 &&
            template.body.indexOf('?}}') === -1 &&
            template.body.indexOf('{{^') === -1 &&
            template.body.indexOf('{{!') === -1 &&
            template.body.indexOf('{{>') === -1;
        if (notUsingMustache) {
            // Convert {{{ }}} variables to Twig format
            template.body = template.body.replace(/{{{/g, '{{').replace(/}}}/g, '|raw}}');

            return 'twig';
        }

        return 'mustache';
    }
})();
