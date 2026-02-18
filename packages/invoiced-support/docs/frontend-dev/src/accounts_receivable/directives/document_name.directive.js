(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('documentName', documentName);

    function documentName() {
        return {
            restrict: 'E',
            template:
                '{{document.number}}' +
                "<small ng-if=\"document.name&&document.name!='Invoice'&&document.name!='Estimate'&&document.name!='Credit Note'\"> {{document.name}}</small>",
            scope: {
                document: '=',
            },
        };
    }
})();
