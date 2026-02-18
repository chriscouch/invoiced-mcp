(function () {
    'use strict';

    angular.module('app.metadata').directive('automationFieldInput', automationFieldInput);

    function automationFieldInput() {
        return {
            restrict: 'E',
            template:
                '<textarea id="message" class="form-control" ng-model="$parent.value" expanding-textarea tabindex="{{inputTabIndex}}" ng-required="required" ng-if="fieldType==\'text\'"></textarea>' +
                '<input type="number" step="any" autocomplete="off" class="form-control" ng-model="$parent.value" ng-if="fieldType==\'double\'" tabindex="{{inputTabIndex}}" placeholder="{{placeholder}}"  ng-required="required"/>' +
                '<div class="invoiced-select" ng-if="fieldType==\'enum\'">' +
                '<select ng-model="$parent.value" ng-options="choice for choice in choices" tabindex="{{inputTabIndex}}" ng-required="required"></select>' +
                '</div>' +
                '<div class="input-group" ng-if="fieldType==\'date\'">' +
                '<input type="text" class="form-control" ng-model="$parent.value" ui-date="dateOptions" is-open="dpOpened" readonly="readonly" tabindex="{{inputTabIndex}}" placeholder="{{placeholder}}" ng-required="required"/>' +
                '<span class="input-group-btn">' +
                '<button type="button" class="btn btn-primary" ng-click="openDatepicker($event, \'dpOpened\')">' +
                '<span class="fas fa-calendar-alt"></span>' +
                '</button>' +
                '</span>' +
                '</div>' +
                '<div ng-if="fieldType==\'boolean\'">' +
                '<toggle ng-model="$parent.value" tabindex="{{inputTabIndex}}"></toggle>' +
                '</div>' +
                '<div ng-model="$parent.value" currency="currency" input-amount ng-if="fieldType==\'money\'" ng-tabindex="inputTabIndex" ng-required="required"></div>',
            scope: {
                field: '=',
                properties: '=',
                value: '=',
                setDefaults: '=?',
                inputTabIndex: '=',
                placeholder: '=',
            },
            controller: [
                '$scope',
                '$attrs',
                '$timeout',
                'MetadataCaster',
                'selectedCompany',
                'DatePickerService',
                function ($scope, $attrs, $timeout, MetadataCaster, selectedCompany, DatePickerService) {
                    $scope.required = typeof $attrs.required !== 'undefined';
                    $scope.choices = [];

                    const dispatch = function () {
                        if ($scope.field && $scope.properties) {
                            changeFieldType($scope.properties.find(property => property.id === $scope.field));
                        }
                    };

                    const listener = $scope.$watch('properties', function () {
                        if ($scope.properties !== undefined) {
                            listener();
                            dispatch();
                        }
                    });
                    $scope.$watch('field', function () {
                        dispatch();
                    });

                    $scope.currency = selectedCompany.currency;

                    $scope.dateOptions = DatePickerService.getOptions();

                    $scope.openDatepicker = function ($event, name) {
                        $event.stopPropagation();
                        $scope[name] = true;
                        // this is needed to ensure the datepicker
                        // can be opened again
                        $timeout(function () {
                            $scope[name] = false;
                        });
                    };

                    function changeFieldType(field) {
                        const type = field.type;

                        $scope.fieldType = 'text';
                        if (
                            type === 'date' ||
                            type === 'money' ||
                            type === 'enum' ||
                            type === 'double' ||
                            type === 'boolean'
                        ) {
                            $scope.fieldType = type;
                        }
                        $scope.choices = field.choices;

                        if ($scope.value) {
                            $scope.value = MetadataCaster.marshalForInput(field, $scope.value);
                        } else if ($scope.setDefaults === true && $scope.fieldType === 'boolean') {
                            $scope.value = MetadataCaster.marshalForInput(field, false);
                        }
                    }
                },
            ],
        };
    }
})();
