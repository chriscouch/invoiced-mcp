(function () {
    'use strict';

    angular.module('app.components').directive('emailFromIcon', emailFromIcon);

    function emailFromIcon() {
        return {
            restrict: 'E',
            template:
                '<div class="email-from-icon">' +
                '<img ng-src="{{logo}}" class="img-circle" ng-if="type==\'logo\'" />' +
                '<img avatar="avatarOptions" user="user" class="img-circle" ng-if="type==\'initials\'" />' +
                '</div>',
            scope: {
                email: '=',
            },
            controller: [
                '$scope',
                'selectedCompany',
                'CurrentUser',
                function ($scope, selectedCompany, CurrentUser) {
                    $scope.logo = selectedCompany.logo;
                    $scope.avatarOptions = {
                        height: 35,
                        width: 35,
                    };

                    $scope.$watch('email', function (email) {
                        $scope.type = 'initials';
                        $scope.user = {
                            name: email.from.name || email.from.email_address,
                        };

                        if (email.incoming && $scope.logo) {
                            $scope.type = 'logo';
                        } else if (isMe(email)) {
                            $scope.user = CurrentUser.profile;
                        }
                    });

                    function isMe(email) {
                        return email.from.email_address === CurrentUser.profile.email;
                    }
                },
            ],
        };
    }
})();
