(function () {
    'use strict';

    angular.module('app.settings').factory('LateFeeSchedule', LateFeeSchedule);

    LateFeeSchedule.$inject = ['$resource', 'InvoicedConfig'];

    function LateFeeSchedule($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/late_fee_schedules/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                create: {
                    method: 'POST',
                },
                assign: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/late_fee_schedules/:id/customers',
                },
                run: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/late_fee_schedules/:id/runs',
                },
                edit: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
            },
        );
    }
})();
