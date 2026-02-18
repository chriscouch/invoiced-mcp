/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('CustomFieldsSettingsController', CustomFieldsSettingsController);

    CustomFieldsSettingsController.$inject = [
        '$scope',
        '$modal',
        'LeavePageWarning',
        'CustomField',
        'selectedCompany',
        'Core',
    ];

    function CustomFieldsSettingsController($scope, $modal, LeavePageWarning, CustomField, selectedCompany, Core) {
        $scope.company = angular.copy(selectedCompany);

        $scope.customFields = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.editCustomFieldModal = function (field) {
            LeavePageWarning.block();

            field = field || false;

            const modalInstance = $modal.open({
                templateUrl: 'metadata/views/edit-custom-field.html',
                controller: 'EditCustomFieldController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        if (!field) {
                            return false;
                        }

                        return field;
                    },
                    currency: function () {
                        return $scope.company.currency;
                    },
                },
            });

            modalInstance.result.then(
                function (_field) {
                    LeavePageWarning.unblock();

                    if (field) {
                        angular.extend(field, _field);
                    } else {
                        $scope.customFields.push(_field);
                    }

                    sortFields();
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (field) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this custom field?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[field.internal_id] = true;
                        $scope.error = null;

                        CustomField.delete(
                            {
                                id: field.internal_id,
                            },
                            function () {
                                $scope.deleting[field.internal_id] = false;

                                // remove locally
                                for (let i in $scope.customFields) {
                                    if ($scope.customFields[i].internal_id == field.internal_id) {
                                        $scope.customFields.splice(i, 1);
                                        break;
                                    }
                                }

                                CustomField.clearCache();
                            },
                            function (result) {
                                $scope.deleting[field.internal_id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Custom Fields');

        load();

        function load() {
            $scope.loading = true;

            CustomField.all(
                function (customFields) {
                    $scope.loading = false;
                    $scope.customFields = customFields;
                    sortFields();
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function sortFields() {
            // sorts fields by object and then name
            $scope.customFields.sort(function (a, b) {
                if (a.object === null) {
                    a.object = '';
                }

                if (b.object === null) {
                    b.object = '';
                }

                if (a.object !== b.object) {
                    return a.object < b.object ? -1 : 1;
                }

                // case-insensitive sort
                let aName = a.name.toLowerCase();
                let bName = b.name.toLowerCase();
                if (aName !== bName) {
                    return aName < bName ? -1 : 1;
                }

                return 0;
            });

            let lastObject = false;
            angular.forEach($scope.customFields, function (customField) {
                if (lastObject === false || customField.object !== lastObject) {
                    lastObject = customField.object;
                    customField.isFirst = true;
                } else {
                    customField.isFirst = false;
                }
            });
        }
    }
})();
