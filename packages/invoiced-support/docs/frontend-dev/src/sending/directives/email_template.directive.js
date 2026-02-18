(function () {
    'use strict';

    angular.module('app.metadata').directive('emailTemplate', emailTemplate);

    function emailTemplate() {
        return {
            restrict: 'E',
            template:
                '<label class="control-label">Email Template <span className="required"></span></label>' +
                '<div class="invoiced-select">' +
                '<select ng-options="template.id as template.name for template in emailTemplates" ng-model="model" required></select>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-link" ng-click="newEmailTemplate()">' +
                '<span class="fas fa-plus"></span>' +
                'New' +
                '</button>',
            scope: {
                model: '=',
                type: '=',
                name: '=',
            },
            controller: [
                '$scope',
                '$modal',
                'EmailTemplate',
                'LeavePageWarning',
                function ($scope, $modal, EmailTemplate, LeavePageWarning) {
                    loadEmailTemplates();
                    function loadEmailTemplates() {
                        $scope.loading++;

                        $scope.emailTemplates = [];
                        EmailTemplate.findAll(
                            {
                                'filter[type]': $scope.type,
                                paginate: 'none',
                            },
                            function (templates) {
                                $scope.loading--;
                                $scope.emailTemplates = templates;
                            },
                            function (result) {
                                $scope.loading--;
                                $scope.error = result.data;
                            },
                        );
                    }

                    $scope.newEmailTemplate = function () {
                        LeavePageWarning.block();

                        const modalInstance = $modal.open({
                            templateUrl: 'sending/views/edit-email-template.html',
                            controller: 'EditEmailTemplateController',
                            size: 'lg',
                            backdrop: 'static',
                            keyboard: false,
                            resolve: {
                                template: function () {
                                    return {
                                        name: $scope.name,
                                        type: $scope.type,
                                        subject: 'Account statement from {{company_name}}',
                                        body: 'Hi {{customer_contact_name}},\n\nYou currently have an outstanding account balance of {{account_balance}} that is now due.\n\nWe appreciate your business!',
                                        options: {},
                                    };
                                },
                                options: function () {
                                    return {
                                        canEditName: true,
                                        canEditType: false,
                                    };
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (template) {
                                $scope.emailTemplates.push(template);
                                $scope.model = template.id;
                                LeavePageWarning.unblock();
                            },
                            function () {
                                // canceled
                                LeavePageWarning.unblock();
                            },
                        );
                    };
                },
            ],
        };
    }
})();
