(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToLawpay', connectToLawpay);

    function connectToLawpay() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="btn btn-primary" ng-click="go()">Connect to {{(\'payment_gateways.lawpay\')|translate}}</a>',
            controller: [
                '$scope',
                '$window',
                'InvoicedConfig',
                'selectedCompany',
                'LeavePageWarning',
                function ($scope, $window, InvoicedConfig, selectedCompany, LeavePageWarning) {
                    $scope.go = function () {
                        LeavePageWarning.unblock();
                        $window.location = InvoicedConfig.baseUrl + '/oauth/lawpay/start?company=' + selectedCompany.id;
                    };
                },
            ],
        };
    }
})();
