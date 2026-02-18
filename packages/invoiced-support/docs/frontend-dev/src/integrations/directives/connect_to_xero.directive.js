(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToXero', connectToXero);

    function connectToXero() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'accounting_sync\'|hasFeature)"></feature-upgrade>' +
                '<a href="{{connectUrl}}" ng-if="\'accounting_sync\'|hasFeature">' +
                '<img height="35" src="/img/xero-connect@2x.svg" alt="Connect to Xero" />' +
                '</a>',
            scope: {
                redirect: '=?',
            },
            controller: [
                '$scope',
                'AppDirectory',
                'selectedCompany',
                function ($scope, AppDirectory, selectedCompany) {
                    $scope.connectUrl = AppDirectory.get('xero').connectUrl + '?company=' + selectedCompany.id;
                    if ($scope.redirect) {
                        $scope.connectUrl += '&r=' + $scope.redirect;
                    }
                },
            ],
        };
    }
})();
