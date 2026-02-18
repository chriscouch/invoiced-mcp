(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToGocardless', connectToGocardless);

    function connectToGocardless() {
        return {
            restrict: 'E',
            template:
                '<a class="btn btn-success connect-gocardless" href="" ng-click="go()">' +
                'Connect with GoCardless' +
                '</a>',
            controller: [
                '$scope',
                '$window',
                'InvoicedConfig',
                'selectedCompany',
                'LeavePageWarning',
                function ($scope, $window, InvoicedConfig, selectedCompany, LeavePageWarning) {
                    $scope.go = function () {
                        LeavePageWarning.unblock();
                        $window.location =
                            InvoicedConfig.baseUrl + '/oauth/gocardless/start?company=' + selectedCompany.id;
                    };
                },
            ],
        };
    }
})();
