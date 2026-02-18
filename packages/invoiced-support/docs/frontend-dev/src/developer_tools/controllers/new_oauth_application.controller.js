(function () {
    'use strict';

    angular.module('app.developer_tools').controller('NewOAuthApplicationController', NewOAuthApplicationController);

    NewOAuthApplicationController.$inject = ['$scope', '$modalInstance', 'OAuthApplication', 'application'];

    function NewOAuthApplicationController($scope, $modalInstance, OAuthApplication, application) {
        if (application) {
            $scope.application = angular.copy(application);
            $scope.application.redirect_uris = [];
            angular.forEach(application.redirect_uris, function (uri) {
                $scope.application.redirect_uris.push({ url: uri });
            });
        } else {
            $scope.application = {
                redirect_uris: [{ url: '' }],
            };
        }

        $scope.addRedirectUri = function (application) {
            application.redirect_uris.push({ url: '' });
        };

        $scope.deleteRedirectUri = function (application, $index) {
            application.redirect_uris.splice($index, 1);
        };

        $scope.save = function (application) {
            $scope.saving = true;
            $scope.error = null;

            const uris = [];
            angular.forEach(application.redirect_uris, function (row) {
                uris.push(row.url);
            });

            if (application.id) {
                OAuthApplication.edit(
                    {
                        id: application.id,
                        include: 'secret',
                    },
                    {
                        name: application.name,
                        redirect_uris: uris,
                    },
                    function (_application) {
                        $scope.saving = false;
                        $modalInstance.close(_application);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            } else {
                OAuthApplication.create(
                    {
                        include: 'secret',
                    },
                    {
                        name: application.name,
                        redirect_uris: uris,
                    },
                    function (_application) {
                        $scope.saving = false;
                        $modalInstance.close(_application);
                    },
                    function (result) {
                        $scope.saving = false;
                        $scope.error = result.data;
                    },
                );
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });
    }
})();
