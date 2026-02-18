/* globals inflection */
(function () {
    'use strict';

    angular.module('app.metadata').directive('metadataName', metadataName);

    function metadataName() {
        return {
            restrict: 'E',
            template: '{{name}}',
            scope: {
                fieldId: '=',
                objectType: '=',
            },
            controller: [
                '$scope',
                'CustomField',
                function ($scope, CustomField) {
                    // this is the default value until we can match it to
                    // a custom field, that may not exist
                    $scope.name = inflection.titleize($scope.fieldId).replace('-', ' ');

                    CustomField.all(function (customFields) {
                        angular.forEach(customFields, function (customField) {
                            if (customField.id == $scope.fieldId && customField.object == $scope.objectType) {
                                $scope.name = customField.name;
                            }
                        });
                    });
                },
            ],
        };
    }
})();
