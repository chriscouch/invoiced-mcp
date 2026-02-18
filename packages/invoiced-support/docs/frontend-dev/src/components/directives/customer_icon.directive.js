/* globals gravatar */
(function () {
    'use strict';

    angular.module('app.components').directive('customerIcon', customerIcon);

    function customerIcon() {
        return {
            restrict: 'E',
            template:
                '<div class="customer-icon">' + '<img ng-src="{{url}}" fallback-src="{{fallbackUrl}}"  />' + '</div>',
            scope: {
                customer: '=',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.$watch('customer', function () {
                        $scope.url = getUrl($scope.customer);
                        $scope.fallbackUrl = getUrl($scope.customer, true);
                        if ($scope.url === $scope.fallbackUrl) {
                            $scope.fallbackUrl = null;
                        }
                    });

                    function getUrl(customer, usePersonal) {
                        if (!customer) {
                            return;
                        }

                        // Use Clearbit's logo API for
                        // company logos, if there is a custom domain detected
                        // 200px in size
                        let email = customer.email || '';
                        if (
                            !usePersonal &&
                            (customer.type === 'company' || customer.entity_type === 'company') &&
                            email
                        ) {
                            let domain = getDomain(email);

                            if (domain) {
                                return 'https://logo.clearbit.com/' + domain + '?size=200';
                            }
                        }

                        // Use Gravatar for personal accounts
                        // 200px in size
                        return gravatar(email, 200);
                    }

                    function getDomain(email) {
                        // filter out free email domains
                        let domain = email.substring(email.lastIndexOf('@') + 1);
                        if (
                            domain.match(
                                /^((?!(yahoo|gmail|hotmail|aol|live.com|icloud.com|mac.com|mail.com|me.com|twc.com|mail.ru|bellsouth|rr.com|example.com|earthlink|comcast|verizon|msn|zoho|cox|shaw.ca|outlook.com|gmx.com|usa.com)).)*$/,
                            )
                        ) {
                            return domain;
                        }
                    }
                },
            ],
        };
    }
})();
