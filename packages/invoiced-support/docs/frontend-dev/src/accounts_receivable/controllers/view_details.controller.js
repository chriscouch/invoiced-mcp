/* globals UAParser */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').controller('ViewDetailsController', ViewDetailsController);

    ViewDetailsController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'event', 'Core'];

    function ViewDetailsController($scope, $modalInstance, selectedCompany, event, Core) {
        $scope.company = selectedCompany;
        $scope.event = event;

        angular.forEach(event.message, function (part) {
            if (part.type === 'invoice') {
                $scope.invoiceId = part.id;
            } else if (part.type === 'estimate') {
                $scope.estimateId = part.id;
            } else if (part.type === 'credit_note') {
                $scope.creditNoteId = part.id;
            }
        });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        parseIp(event.data.object.ip);
        parseUserAgent(event.data.object.user_agent);

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
