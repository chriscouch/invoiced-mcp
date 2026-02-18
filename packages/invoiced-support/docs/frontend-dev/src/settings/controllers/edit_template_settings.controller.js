(function () {
    'use strict';

    angular.module('app.settings').controller('EditTemplateSettingsController', EditTemplateSettingsController);

    EditTemplateSettingsController.$inject = ['$scope', '$modalInstance', 'Template', 'template', 'mode'];

    function EditTemplateSettingsController($scope, $modalInstance, Template, template, mode) {
        $scope.mode = mode;

        if (template.id) {
            $scope.template = angular.copy(template);
        } else {
            $scope.template = {
                filename: mode === 'javascript' ? 'billing_portal/index.js' : 'billing_portal/styles.css',
                content: '',
                enabled: true,
            };
        }

        $scope.cmOptions = {
            theme: 'monokai',
            lineNumbers: true,
            lineWrapping: true,
            indentWithTabs: true,
            tabSize: 2,
            matchBrackets: true,
            styleActiveLine: true,
            mode: mode,
        };

        $scope.cmRefresh++;

        $scope.save = function (template) {
            $scope.saving = true;
            $scope.error = null;

            if (template.id) {
                Template.edit(
                    {
                        id: template.id,
                        content: template.content,
                        enabled: template.enabled,
                    },
                    function (template) {
                        $scope.saving = false;
                        $modalInstance.close(template);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                Template.create(
                    template,
                    function (template) {
                        $scope.saving = false;
                        $modalInstance.close(template);
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
    }
})();
