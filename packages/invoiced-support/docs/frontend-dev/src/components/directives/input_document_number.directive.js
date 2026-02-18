(function () {
    'use strict';

    angular.module('app.components').directive('inputDocumentNumber', inputDocumentNumber);

    function inputDocumentNumber() {
        return {
            restrict: 'E',
            template:
                '<div class="input-document-number">' +
                '<div class="addon number-sign">#</div>' +
                '<input class="form-control" type="text" tabindex="{{tabindex}}" placeholder="Generated if blank" ng-model="value" />' +
                '</div>',
            scope: {
                value: '=ngModel',
                tabindex: '=?ngTabindex',
            },
        };
    }
})();
