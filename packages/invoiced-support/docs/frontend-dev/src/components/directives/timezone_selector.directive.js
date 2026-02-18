(function () {
    'use strict';

    angular.module('app.components').directive('timezoneSelector', timezoneSelector);

    function timezoneSelector() {
        let timezones;

        return {
            restrict: 'E',
            template:
                '<div class="invoiced-select">' +
                '<select ng-model="model" ng-options="tz.timezone as tz.city group by tz.continent for tz in timezones" required></select>' +
                '</div>',
            scope: {
                model: '=ngModel',
            },
            controller: [
                'InvoicedConfig',
                '$scope',
                function (InvoicedConfig, $scope) {
                    if (!timezones) {
                        timezones = [];
                        angular.forEach(InvoicedConfig.timezones, function (timezone) {
                            let parts = timezone.replace('_', ' ').split('/');
                            let city = parts[1] || parts[0];
                            if (parts.length > 2) {
                                city += ': ' + parts[2];
                            }

                            timezones.push({
                                continent: parts[0],
                                city: city,
                                timezone: timezone,
                            });
                        });
                    }

                    $scope.timezones = timezones;
                },
            ],
        };
    }
})();
