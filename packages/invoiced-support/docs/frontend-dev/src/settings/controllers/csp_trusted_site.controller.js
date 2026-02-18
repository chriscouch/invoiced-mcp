(function () {
    'use strict';

    angular.module('app.settings').controller('CspTrustedSiteController', CspTrustedSiteController);

    CspTrustedSiteController.$inject = ['$scope', '$modalInstance', 'CspTrustedSite', 'trustedSite'];

    function CspTrustedSiteController($scope, $modalInstance, CspTrustedSite, trustedSite) {
        $scope.trustedSite = angular.copy(trustedSite) || {
            url: '',
            connect: false,
            font: false,
            frame: false,
            img: false,
            media: false,
            object: false,
            script: false,
            style: false,
        };

        $scope.save = function (trustedSite) {
            $scope.saving = true;
            $scope.error = false;
            if (trustedSite.id) {
                edit(trustedSite);
            } else {
                add(trustedSite);
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function edit(trustedSite) {
            CspTrustedSite.edit(
                {
                    id: trustedSite.id,
                },
                {
                    url: trustedSite.url,
                    connect: trustedSite.connect,
                    font: trustedSite.font,
                    frame: trustedSite.frame,
                    img: trustedSite.img,
                    media: trustedSite.media,
                    object: trustedSite.object,
                    script: trustedSite.script,
                    style: trustedSite.style,
                },
                function (_trustedSite) {
                    $scope.saving = false;
                    $modalInstance.close(_trustedSite);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function add(trustedSite) {
            CspTrustedSite.create(
                trustedSite,
                function (_trustedSite) {
                    $scope.saving = false;
                    $modalInstance.close(_trustedSite);
                },
                function (result) {
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }
    }
})();
