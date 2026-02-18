(function () {
    'use strict';

    angular.module('app.catalog').controller('AddCustomFieldController', AddCustomFieldController);

    AddCustomFieldController.$inject = [
        '$scope',
        '$modalInstance',
        '$modal',
        '$timeout',
        'CustomField',
        'Core',
        'selectedCompany',
        'type',
    ];

    function AddCustomFieldController(
        $scope,
        $modalInstance,
        $modal,
        $timeout,
        CustomField,
        Core,
        selectedCompany,
        type,
    ) {
        $scope.customFields = [];
        $scope.company = selectedCompany;

        let allowedType = type ? type : false;

        $scope.newCustomFieldModal = function (name) {
            $('.add-custom-field-modal').hide();

            name = name || '';

            const modalInstance = $modal.open({
                templateUrl: 'metadata/views/edit-custom-field.html',
                controller: 'EditCustomFieldController',
                resolve: {
                    model: function () {
                        return {
                            name: name,
                            object: type ? type : null,
                        };
                    },
                },
                backdrop: false,
                keyboard: false,
            });

            modalInstance.result.then(
                function (newCustomField) {
                    $scope.customFields.push(newCustomField);

                    $('.add-custom-field-modal').show();

                    // select the new custom field
                    $scope.select(newCustomField);
                },
                function () {
                    // canceled
                    $('.add-custom-field-modal').show();
                },
            );
        };

        $scope.select = function (customField) {
            $modalInstance.close(customField);
        };

        $scope.customAmount = function () {
            $modalInstance.close(0);
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        loadCustomFields();

        function loadCustomFields() {
            $scope.loading = true;

            CustomField.all(
                function (customFields) {
                    $scope.loading = false;
                    $scope.customFields = [];

                    angular.forEach(customFields, function (customField) {
                        if (customField.object === allowedType) {
                            $scope.customFields.push(customField);
                        }
                    });

                    // focus searchbar input (after timeout so DOM can render)
                    $timeout(function () {
                        $('.modal-selector .search input').focus();
                    }, 50);
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
