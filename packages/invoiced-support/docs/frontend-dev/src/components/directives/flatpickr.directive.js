// Based off of https://github.com/archsaber/angular-flatpickr
/* globals flatpickr */
(function () {
    'use strict';

    angular.module('app.components').directive('flatpickr', ngFlatpickr);

    function ngFlatpickr() {
        return {
            restrict: 'A',
            require: 'ngModel',
            scope: {
                fpOpts: '&',
                fpOnSetup: '&',
                ngModel: '=?',
            },
            link: function ($scope, element) {
                let vp = new flatpickr(element[0], $scope.fpOpts());

                if ($scope.ngModel) {
                    vp.setDate($scope.ngModel);
                }

                if (typeof $scope.fpOnSetup === 'function') {
                    $scope.fpOnSetup({
                        fpItem: vp,
                    });
                }

                // destroy the flatpickr instance when the dom element is removed
                element.on('$destroy', function () {
                    vp.destroy();
                });
            },
        };
    }
})();
