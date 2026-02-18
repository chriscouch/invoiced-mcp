(function () {
    'use strict';
    angular.module('app.inboxes').directive('emailThreadText', emailThreadText);

    function emailThreadText() {
        return {
            restrict: 'E',
            templateUrl: 'inboxes/views/text.html',
            scope: {
                text: '=',
            },
            controller: [
                '$scope',
                'selectedCompany',
                function ($scope, selectedCompany) {
                    $scope.avatarOptions = {
                        height: 35,
                        width: 35,
                    };

                    $scope.companyName = selectedCompany.name;
                    $scope.companyLogo = selectedCompany.logo;
                },
            ],
        };
    }
})();
