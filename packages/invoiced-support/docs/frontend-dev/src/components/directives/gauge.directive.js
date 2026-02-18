/* globals Gauge */
(function () {
    'use strict';

    angular.module('app.components').directive('gauge', gauge);

    gauge.$inject = ['$timeout'];

    function gauge($timeout) {
        let defaultOpts = {
            angle: 0.15, /// The span of the gauge arc
            lineWidth: 0.4, // The line thickness
            pointer: {
                length: 0,
                strokeWidth: 0,
            },
            highDpiSupport: true,
        };

        return {
            restrict: 'E',
            template: '<canvas class="gauge"></canvas>',
            scope: {
                opts: '=?',
                min: '=?',
                max: '=?',
                value: '=',
            },
            controller: [
                '$scope',
                '$element',
                function ($scope, $element) {
                    let gauge;

                    $scope.$watch('value', function (val) {
                        if (isNaN(val)) {
                            return;
                        }

                        $timeout(function () {
                            if (!gauge) {
                                draw();
                            }
                            gauge.set(val);
                        });
                    });

                    function draw() {
                        let opts = angular.copy(defaultOpts);
                        if ($scope.opts) {
                            angular.extend(opts, $scope.opts);
                        }

                        let target = $('.gauge', $element)[0];
                        gauge = new Gauge(target).setOptions(opts);

                        if ($scope.max) {
                            gauge.maxValue = $scope.max;
                        }

                        if ($scope.min) {
                            gauge.setMinValue($scope.min);
                        }
                    }
                },
            ],
        };
    }
})();
