(function () {
    'use strict';

    angular.module('app.integrations').directive('connectToStripe', connectToStripe);

    function connectToStripe() {
        return {
            restrict: 'E',
            template:
                '<a class="connect-stripe" href="" ng-click="go()">' +
                '<img width="177" height="38" src="/img/stripe-connect.svg" alt="Connect with Stripe" />' +
                '</a>',
            scope: {
                method: '=?',
                redirect: '=?',
            },
            controller: [
                '$scope',
                '$window',
                'InvoicedConfig',
                'selectedCompany',
                'LeavePageWarning',
                function ($scope, $window, InvoicedConfig, selectedCompany, LeavePageWarning) {
                    $scope.go = function () {
                        LeavePageWarning.unblock();
                        let connectUrl =
                            InvoicedConfig.baseUrl +
                            '/oauth/stripe/start?company=' +
                            selectedCompany.id +
                            '&register=1';
                        if ($scope.method) {
                            connectUrl += '&method=' + $scope.method;
                        }
                        if ($scope.redirect) {
                            connectUrl += '&r=' + $scope.redirect;
                        }
                        $window.location = connectUrl;
                    };
                },
            ],
        };
    }
})();
