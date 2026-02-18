(function () {
    'use strict';

    angular.module('app.metadata').directive('metadataValue', metadataValue);

    function metadataValue() {
        return {
            restrict: 'E',
            template: '<span ng-bind-html="stringValue"></span>',
            scope: {
                fieldId: '=',
                objectType: '=',
                value: '=',
            },
            controller: [
                '$scope',
                'CustomField',
                'MetadataCaster',
                function ($scope, CustomField, MetadataCaster) {
                    $scope.stringValue = MetadataCaster.getDisplayValue({ type: 'string' }, $scope.value);

                    CustomField.all(function (customFields) {
                        $scope.$watch('value', function (newValue) {
                            angular.forEach(customFields, function (customField) {
                                if (customField.id == $scope.fieldId && customField.object == $scope.objectType) {
                                    $scope.stringValue = MetadataCaster.getDisplayValue(customField, newValue);
                                }
                            });
                        });
                    });
                },
            ],
        };
    }
})();
