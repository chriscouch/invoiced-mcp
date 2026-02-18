(function () {
    'use strict';

    angular.module('app.automations').directive('automationStepStringify', automationStepStringify);

    function automationStepStringify() {
        return {
            restrict: 'E',
            template:
                '<div class="step-settings" ng-show="step.action_type == \'CreateObject\'">' +
                "{{('metadata.object_names.'+step.settings.object_type)|translate}}" +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'ModifyPropertyValue\'">' +
                "{{('metadata.object_names.'+step.settings.object_type)|translate}} Property: <code>{{step.settings.name}}</code><br/>" +
                'Value: <code>{{step.settings.value}}</code>' +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'CopyPropertyValue\'">' +
                "From {{('metadata.object_names.'+objectType)|translate}} Property: <code>{{step.settings.value}}</code><br/>" +
                "To {{('metadata.object_names.'+step.settings.object_type)|translate}} Property: <code>{{step.settings.name}}</code>" +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'ClearPropertyValue\'">' +
                "{{('metadata.object_names.'+step.settings.object_type)|translate}} Property: <code>{{step.settings.name}}</code>" +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'DeleteObject\'">' +
                "{{('metadata.object_names.'+objectType)|translate}}" +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'SendEmail\'">' +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'SendInternalNotification\'">' +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'Webhook\'">' +
                'URL: <code>{{step.settings.url}}</code>' +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'Condition\'">' +
                "{{('metadata.object_names.'+step.settings.object_type)|translate}} Property: <code>{{step.settings.expression}}</code>" +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'SendDocument\'">' +
                '</div>' +
                '<div class="step-settings" ng-show="step.action_type == \'PostToSlack\'">' +
                'Message: {{step.settings.message}}' +
                '</div>',
            scope: {
                step: '=',
                objectType: '=',
            },
        };
    }
})();
