(function () {
    'use strict';

    angular.module('app.integrations').factory('Slack', Slack);

    Slack.$inject = ['$resource', 'InvoicedConfig'];

    function Slack($resource, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/slack/channels',
            {},
            {
                channels: {
                    method: 'GET',
                    isArray: true,
                },
            },
        );
    }
})();
