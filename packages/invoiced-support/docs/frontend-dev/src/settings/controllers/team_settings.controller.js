(function () {
    'use strict';

    angular.module('app.settings').controller('TeamSettingsController', TeamSettingsController);

    TeamSettingsController.$inject = ['$scope', 'Feature'];

    function TeamSettingsController($scope, Feature) {
        $scope.hasFeature = Feature.hasFeature('roles');
        $scope.hasSamlFeature = Feature.hasFeature('saml');
    }
})();
