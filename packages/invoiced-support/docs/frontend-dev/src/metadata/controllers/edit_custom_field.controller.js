(function () {
    'use strict';

    angular.module('app.metadata').controller('EditCustomFieldController', EditCustomFieldController);

    EditCustomFieldController.$inject = [
        '$scope',
        '$modalInstance',
        'selectedCompany',
        'CustomField',
        'IdGenerator',
        'model',
    ];

    function EditCustomFieldController($scope, $modalInstance, selectedCompany, CustomField, IdGenerator, model) {
        if (model && model.internal_id) {
            $scope.customField = angular.copy(model);
        } else {
            $scope.customField = {
                id: model.name || '',
                name: model.name || '',
                object: model.object || null,
                type: 'string',
                values: model.choices || [],
                external: model.external || true,
                choices: [],
            };
        }

        $scope.company = selectedCompany;
        $scope.currency = $scope.company.currency;
        $scope.shouldGenID = !model.internal_id;
        $scope.isExisting = !!model.internal_id;

        $scope.sortableOptions = {
            handle: '.sortable-handle',
            placholder: 'sortable-placeholder',
        };

        let externalObjects = ['credit_note', 'customer', 'estimate', 'invoice', 'line_item', 'subscription'];

        $scope.generateID = function (model) {
            if (!$scope.shouldGenID && model.internal_id) {
                return;
            }

            $scope.shouldGenID = true;
            if (!model.name) {
                model.id = '';
                return;
            }

            // generate ID as the user types the name
            // i.e. Account Owner -> account_owner
            model.id = IdGenerator.generate(model.name, '_');
        };

        $scope.choiceKeypress = function ($event) {
            if ($event.keyCode == 13) {
                $event.preventDefault();
                $scope.addChoice($scope.customField, $scope.newChoice);
            }
        };

        $scope.addChoice = function (customField, choice) {
            if (!choice) {
                return;
            }

            // prevent duplicates
            for (let i in customField.choices) {
                if (customField.choices[i] == choice) {
                    return;
                }
            }

            // clear out selected choice
            $scope.newChoice = '';

            customField.choices.push(choice);
        };

        $scope.deleteChoice = function (customField, choice) {
            for (let i in customField.choices) {
                if (customField.choices[i] == choice) {
                    customField.choices.splice(i, 1);
                    break;
                }
            }
        };

        $scope.fieldCanBeExternal = function fieldCanBeExternal(obj) {
            return externalObjects.indexOf(obj) !== -1;
        };

        $scope.save = function (customField) {
            $scope.saving = true;
            $scope.error = null;
            customField = angular.copy(customField);

            // parse choices
            if (customField.type !== 'enum') {
                customField.choices = [];
            }

            if (!$scope.fieldCanBeExternal(customField.object)) {
                customField.external = false;
            }

            if ($scope.isExisting) {
                CustomField.edit(
                    {
                        id: customField.internal_id,
                    },
                    {
                        name: customField.name,
                        type: customField.type,
                        choices: customField.choices,
                        external: customField.external,
                    },
                    function (_customField) {
                        $scope.saving = false;

                        $modalInstance.close(_customField);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                CustomField.create(
                    {},
                    customField,
                    function (_customField) {
                        $scope.saving = false;

                        $modalInstance.close(_customField);
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

        $scope.generateID($scope.customField);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
