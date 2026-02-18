(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToQuickbooks', connectToQuickbooks);

    function connectToQuickbooks() {
        return {
            restrict: 'E',
            template:
                '<feature-upgrade upgrade-class="\'inline-upgrade\'" ng-if="!(\'accounting_sync\'|hasFeature)"></feature-upgrade>' +
                '<a href="" ng-click="go()" class="qbo-connect-button" ng-if="\'accounting_sync\'|hasFeature">' +
                '</a>',
            scope: {
                redirect: '=?',
                withPayments: '=?',
            },
            controller: [
                '$scope',
                '$window',
                'AppDirectory',
                'selectedCompany',
                'LeavePageWarning',
                function ($scope, $window, AppDirectory, selectedCompany, LeavePageWarning) {
                    $scope.go = function () {
                        LeavePageWarning.unblock();
                        let connectUrl =
                            AppDirectory.get('quickbooks_online').connectUrl + '?company=' + selectedCompany.id;
                        if ($scope.redirect) {
                            connectUrl += '&r=' + $scope.redirect;
                        }
                        if ($scope.withPayments) {
                            connectUrl += '&payments=1';
                        }
                        $window.location = connectUrl;
                    };
                },
            ],
        };
    }
})();
