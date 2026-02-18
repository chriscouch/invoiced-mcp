(function () {
    'use strict';

    angular.module('app.imports').controller('ImportChoicesController', ImportChoicesController);

    ImportChoicesController.$inject = ['$scope', 'Core', 'title', 'choices'];

    function ImportChoicesController($scope, Core, title, choices) {
        $scope.choices = choices;
        Core.setTitle(title);
    }
})();
