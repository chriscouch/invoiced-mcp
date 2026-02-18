(function () {
    'use strict';

    angular.module('app.metadata').directive('customFieldInput', customFieldInput);

    function customFieldInput() {
        return {
            restrict: 'E',
            template:
                '<input type="text" class="form-control" ng-model="$parent.metadata[field.id]" ng-if="fieldType==\'text\'" tabindex="{{inputTabIndex}}" placeholder="{{placeholder}}" />' +
                '<input type="number" step="any" autocomplete="off" class="form-control" ng-model="$parent.metadata[field.id]" ng-if="fieldType==\'double\'" tabindex="{{inputTabIndex}}" placeholder="{{placeholder}}" />' +
                '<div class="invoiced-select" ng-if="fieldType==\'enum\'">' +
                '<select ng-model="$parent.metadata[field.id]" ng-options="choice for choice in field.choices" tabindex="{{inputTabIndex}}"></select>' +
                '</div>' +
                '<div class="input-group" ng-if="fieldType==\'date\'">' +
                '<input type="text" class="form-control" ng-model="$parent.metadata[field.id]" ui-date="dateOptions" is-open="dpOpened" readonly="readonly" tabindex="{{inputTabIndex}}" placeholder="{{placeholder}}" />' +
                '<span class="input-group-btn">' +
                '<button type="button" class="btn btn-primary" ng-click="openDatepicker($event, \'dpOpened\')">' +
                '<span class="fas fa-calendar-alt"></span>' +
                '</button>' +
                '</span>' +
                '</div>' +
                '<div ng-if="fieldType==\'boolean\'">' +
                '<toggle ng-model="$parent.metadata[field.id]" tabindex="{{inputTabIndex}}"></toggle>' +
                '</div>' +
                '<div ng-model="$parent.metadata[field.id]" currency="currency" input-amount ng-if="fieldType==\'money\'" ng-tabindex="inputTabIndex"></div>',
            scope: {
                field: '=',
                metadata: '=',
                setDefaults: '=?',
                inputTabIndex: '=',
                placeholder: '=',
            },
            controller: [
                '$scope',
                '$timeout',
                'MetadataCaster',
                'selectedCompany',
                'DatePickerService',
                function ($scope, $timeout, MetadataCaster, selectedCompany, DatePickerService) {
                    if (typeof $scope.metadata !== 'object') {
                        return;
                    }

                    $scope.fieldType = 'text';
                    if (
                        $scope.field.type === 'date' ||
                        $scope.field.type === 'money' ||
                        $scope.field.type === 'enum' ||
                        $scope.field.type === 'double' ||
                        $scope.field.type === 'boolean'
                    ) {
                        $scope.fieldType = $scope.field.type;
                    }

                    if (typeof $scope.metadata[$scope.field.id] !== 'undefined') {
                        $scope.metadata[$scope.field.id] = MetadataCaster.marshalForInput(
                            $scope.field,
                            $scope.metadata[$scope.field.id],
                        );
                    } else if ($scope.setDefaults === true && $scope.fieldType === 'boolean') {
                        $scope.metadata[$scope.field.id] = MetadataCaster.marshalForInput($scope.field, false);
                    }

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
                },
            ],
        };
    }
})();
