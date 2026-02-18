(function () {
    'use strict';
    angular.module('app.inboxes').directive('emailThreadLetter', emailThreadLetter);

    function emailThreadLetter() {
        return {
            restrict: 'E',
            templateUrl: 'inboxes/views/letter.html',
            scope: {
                letter: '=',
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
