/* globals UAParser */
(function () {
    'use strict';

    angular
        .module('app.accounts_receivable')
        .controller('EstimateApprovalDetailsController', EstimateApprovalDetailsController);

    EstimateApprovalDetailsController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'approval', 'Core'];

    function EstimateApprovalDetailsController($scope, $modalInstance, selectedCompany, approval, Core) {
        $scope.company = selectedCompany;
        $scope.approval = approval;

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        parseIp(approval.ip);
        parseUserAgent(approval.user_agent);

        function parseIp(ip) {
            Core.lookupIp(ip, function (result) {
                $scope.$apply(function () {
                    let el = [result.city, result.region, result.country];
                    $scope.location = el
                        .filter(function (n) {
                            return n;
                        })
                        .join(', ');
                });
            });
        }

        function parseUserAgent(ua) {
            let parser = new UAParser();
            parser.setUA(ua);
            let result = parser.getResult();
            if (result) {
                let el = [result.device.type, result.browser.name, result.os.name];
                $scope.userAgent = el
                    .filter(function (n) {
                        return n;
                    })
                    .join('/');
            }
        }
    }
})();
