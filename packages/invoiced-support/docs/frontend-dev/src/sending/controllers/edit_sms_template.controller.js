(function () {
    'use strict';

    angular.module('app.sending').controller('EditSmsTemplateController', EditSmsTemplateController);

    EditSmsTemplateController.$inject = ['$scope', '$modalInstance', 'SmsTemplate', 'template', 'Core'];

    function EditSmsTemplateController($scope, $modalInstance, SmsTemplate, template, Core) {
        $scope.template = angular.copy(template);

        let defaultVariable = '-- Insert Variable';
        $scope.variable = defaultVariable;

        $scope.variables = [
            '-- Insert Variable',
            '{{company_name}}',
            '{{contact_name}}',
            '{{customer_name}}',
            '{{customer_number}}',
            '{{account_balance}}',
            '{{url}}',
        ];

        $scope.addVariable = function (variable, type) {
            if (variable === defaultVariable) {
                return;
            }

            let el = $('#sms-template-body');
            let pos = el.caret();

            // inject at position in template
            $scope.template[type] = spliceSlice(el.val(), pos, 0, variable);

            // reset the selection
            $scope.variable = defaultVariable;
        };

        $scope.save = function (_template) {
            $scope.saving = true;

            let params = angular.copy(_template);

            if (params.id) {
                SmsTemplate.edit(
                    {
                        id: _template.id,
                    },
                    {
                        name: _template.name,
                        message: _template.message,
                    },
                    function () {
                        $scope.error = null;
                        $scope.saving = false;

                        Core.flashMessage('Your SMS template has been updated.', 'success');

                        $modalInstance.close(_template);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                SmsTemplate.create(
                    params,
                    function (_template2) {
                        $scope.error = null;
                        $scope.saving = false;

                        Core.flashMessage('Your SMS template has been created.', 'success');

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

        function spliceSlice(str, index, count, add) {
            return str.slice(0, index) + (add || '') + str.slice(index + count);
        }
    }
})();
