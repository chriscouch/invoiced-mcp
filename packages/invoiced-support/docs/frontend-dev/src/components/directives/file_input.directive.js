(function () {
    'use strict';

    angular.module('app.components').directive('fileInput', fileInput);

    function fileInput() {
        return {
            restrict: 'EA',
            require: 'ngModel',
            link: function (scope, element, attrs, ngModelCtrl) {
                let fileInput = element[0].querySelector('input[type=file]');

                fileInput.addEventListener('change', handleFileInput);

                scope.$on('$destroy', function () {
                    fileInput.removeEventListener('change', handleFileInput);
                });

                function handleFileInput() {
                    if (!fileInput.files || !fileInput.files[0]) {
                        return;
                    }

                    let loadedFile = fileInput.files[0];

                    scope.$apply(function () {
                        ngModelCtrl.$setViewValue(loadedFile);
                    });
                }
            },
        };
    }
})();
