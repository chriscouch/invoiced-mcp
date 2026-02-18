(function () {
    'use strict';

    angular.module('app.core').factory('AutoNumberSequence', AutoNumberSequence);

    AutoNumberSequence.$inject = ['$resource', 'InvoicedConfig'];

    function AutoNumberSequence($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/auto_number_sequences/:type',
            {
                type: '@type',
            },
            {
                find: {
                    method: 'GET',
                },
                edit: {
                    method: 'PATCH',
                },
            },
        );
    }
})();
