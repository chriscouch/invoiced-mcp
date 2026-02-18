(function () {
    'use strict';

    angular.module('app.inboxes').directive('namedEmailAddress', namedEmailAddress);

    function namedEmailAddress() {
        return {
            restrict: 'E',
            template:
                '<span class="named-email-address">' +
                '<span class="name">{{name}}</span> ' +
                '<span class="email" ng-hide="nameOnly"><{{address.email_address}}></span>' +
                '</span>',
            scope: {
                address: '=',
            },
            controller: [
                '$scope',
                'selectedCompany',
                function ($scope, selectedCompany) {
                    $scope.$watch('address', function (email) {
                        $scope.nameOnly = false;
                        if (typeof email.name !== 'undefined' && email.name) {
                            $scope.name = email.name;
                            $scope.nameOnly = showNameOnly(email);
                        } else {
                            $scope.name = email.email_address;
                            $scope.nameOnly = true;
                        }
                    });

                    function showNameOnly(email) {
                        if (typeof email.name === 'undefined' || !email.name) {
                            return false;
                        }

                        // Show the name only if this is the company email address
                        // or has invoicedmail.com in it, indicating an email was
                        // sent to another Invoiced inbox.
                        return (
                            email.email_address === selectedCompany.email ||
                            email.email_address.indexOf('invoicedmail.com') !== -1
                        );
                    }
                },
            ],
        };
    }
})();
