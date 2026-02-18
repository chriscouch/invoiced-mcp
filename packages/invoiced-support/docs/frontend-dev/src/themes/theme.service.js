(function () {
    'use strict';

    angular.module('app.themes').factory('Theme', ThemeService);

    ThemeService.$inject = ['$resource', 'InvoicedConfig'];

    function ThemeService($resource, InvoicedConfig) {
        let Theme = {
            defaultTheme: angular.copy(InvoicedConfig.themes.default),
        };

        angular.extend(
            Theme,
            $resource(
                InvoicedConfig.apiBaseUrl + '/themes/:id',
                {},
                {
                    findAll: {
                        method: 'GET',
                        isArray: true,
                    },
                    find: {
                        method: 'GET',
                    },
                    create: {
                        method: 'POST',
                        url: InvoicedConfig.apiBaseUrl + '/themes',
                    },
                    edit: {
                        method: 'PATCH',
                    },
                    delete: {
                        method: 'DELETE',
                    },
                },
            ),
        );

        return Theme;
    }
})();
