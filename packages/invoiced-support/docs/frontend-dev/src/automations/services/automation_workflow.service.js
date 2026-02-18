(function () {
    'use strict';

    angular.module('app.automations').factory('AutomationWorkflow', AutomationWorkflow);

    AutomationWorkflow.$inject = ['$resource', 'InvoicedConfig'];

    function AutomationWorkflow($resource, InvoicedConfig) {
        const resource = $resource(
            InvoicedConfig.apiBaseUrl + '/automation_workflows/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        sort: 'name ASC',
                    },
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                },
                manualTrigger: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/manual_trigger',
                },
                massTrigger: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/manual_trigger/:id',
                    params: {
                        id: '@id',
                    },
                },
                edit: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
                createVersion: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/:workflow_id/versions',
                },
                editVersion: {
                    method: 'PATCH',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/:workflow_id/versions/:id',
                    params: {
                        id: '@id',
                        workflow_id: '@workflow_id',
                    },
                },
                enroll: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/enrollment',
                    params: {
                        object_id: '@id',
                        workflow: '@workflow_id',
                    },
                },
                unEnroll: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/enrollment/:id',
                    params: {
                        id: '@id',
                    },
                },
                massEnroll: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/enrollments/:id',
                    params: {
                        id: '@id',
                    },
                },
                massUnEnroll: {
                    method: 'DELETE',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflows/enrollments/:id',
                    params: {
                        id: '@id',
                        options: '@options',
                    },
                },
                runs: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflow_runs',
                    isArray: true,
                    params: {
                        id: '@id',
                    },
                },
                run: {
                    method: 'GET',
                    url: InvoicedConfig.apiBaseUrl + '/automation_workflow_runs/:id',
                    params: {
                        id: '@id',
                    },
                },
            },
        );

        resource.loadAutomations = function (objectType, success, error) {
            resource.findAll(
                {
                    'filter[enabled]': 1,
                    'filter[object_type]': objectType,
                    expand: 'current_version',
                },
                function (workflows) {
                    const automations = workflows.reduce(function (accumulator, currentValue) {
                        if (
                            currentValue.current_version.triggers.some(trigger => trigger.trigger_type === 'Schedule')
                        ) {
                            accumulator.push({
                                value: currentValue.id.toString(),
                                text: currentValue.name,
                            });
                        }

                        return accumulator;
                    }, []);

                    success(automations);
                },
                error,
            );
        };

        return resource;
    }
})();
