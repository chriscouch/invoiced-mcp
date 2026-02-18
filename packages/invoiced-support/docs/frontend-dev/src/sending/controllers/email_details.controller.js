/* globals UAParser */
(function () {
    'use strict';

    angular.module('app.sending').controller('EmailDetailsController', EmailDetailsController);

    EmailDetailsController.$inject = ['$scope', '$modalInstance', 'selectedCompany', 'Email', 'event', 'Core'];

    function EmailDetailsController($scope, $modalInstance, selectedCompany, Email, event, Core) {
        $scope.company = selectedCompany;
        $scope.event = event;

        angular.forEach($scope.event.message, function (part) {
            if (part.type == 'email') {
                $scope.template = part.value;

                if (part.object == 'customer') {
                    $scope.customerId = part.object_id;
                } else if (part.object == 'invoice') {
                    $scope.invoiceId = part.object_id;
                } else if (part.object == 'estimate') {
                    $scope.estimateId = part.object_id;
                } else if (part.object == 'credit_note') {
                    $scope.creditNoteId = part.object_id;
                } else if (part.object == 'payment' || part.object == 'transaction') {
                    $scope.paymentId = part.object_id;
                } else if (part.object == 'subscription') {
                    $scope.subscriptionId = part.object_id;
                }
            }
        });

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        load($scope.event);

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function load(event) {
            $scope.loading = true;

            Email.find(
                {
                    id: event.data.object.id,
                },
                function (email) {
                    angular.forEach(email.opens_detail, parseEvent);

                    $scope.email = email;

                    $scope.loading = false;
                },
                function () {
                    // could not load email info
                    $scope.email = false;
                    $scope.loading = false;
                },
            );
        }

        let parser;

        function parseEvent(obj) {
            // parse location
            Core.lookupIp(obj.ip, function (result) {
                $scope.$apply(function () {
                    let el = [result.city, result.region, result.country];
                    obj.loc = el
                        .filter(function (n) {
                            return n;
                        })
                        .join(', ');
                });
            });

            // parse user agent
            if (!parser) {
                parser = new UAParser();
            }
            parser.setUA(obj.user_agent);
            let result = parser.getResult();
            if (result) {
                let el = [result.device.type, result.browser.name, result.os.name];
                obj.ua = el
                    .filter(function (n) {
                        return n;
                    })
                    .join('/');
            }
        }
    }
})();
