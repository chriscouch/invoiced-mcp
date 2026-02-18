/* globals moment */
(function () {
    'use strict';

    angular.module('app.content').factory('Announcement', Announcement);

    Announcement.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function Announcement($resource, $http, InvoicedConfig) {
        return $resource(
            InvoicedConfig.apiBaseUrl + '/announcements',
            {},
            {
                get: {
                    method: 'GET',
                    cache: true,
                    isArray: true,
                    transformResponse: $http.defaults.transformResponse.concat(function (response, headers, status) {
                        if (status !== 200) {
                            return response;
                        }

                        let announcements = response;

                        angular.forEach(announcements, function (announcement) {
                            announcement.date = moment.unix(announcement.date).toDate();
                        });

                        return announcements;
                    }),
                },
            },
        );
    }
})();
