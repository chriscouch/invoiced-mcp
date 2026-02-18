(function () {
    'use strict';
    angular.module('app.core').directive('fileItem', fileItem);

    fileItem.inject = ['$filter'];

    function fileItem($parse) {
        return {
            restrict: 'A', //the directive can be used as an attribute only
            /*
             link is a function that defines functionality of directive
             scope: scope associated with the element
             element: element on which this directive used
             attrs: key value pair of element attributes
             */
            link: function (scope, element, attrs) {
                const model = $parse(attrs.fileItem);

                //Bind change event on the element
                element.bind('change', function () {
                    //Call apply on scope, it checks for value changes and reflect them on UI
                    scope.$apply(function () {
                        model.assign(scope, element[0].files[0]);
                    });
                });
            },
        };
    }
})();
