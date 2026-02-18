(function () {
    'use strict';

    angular.module('app.core').controller('NoCompaniesController', NoCompaniesController);

    NoCompaniesController.$inject = ['$scope', '$modal', 'Core', 'InvoicedConfig'];

    function NoCompaniesController($scope, $modal, Core, InvoicedConfig) {
        Core.setTitle('No Companies');

        $('html').addClass('gray-bg');

        $scope.baseUrl = InvoicedConfig.baseUrl;

        $scope.addBusiness = function () {
            $modal.open({
                templateUrl: 'core/views/add-business.html',
                controller: 'AddBusinessController',
                backdrop: 'static',
                keyboard: false,
            });
        };
    }
})();
