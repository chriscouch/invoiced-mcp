(function () {
    'use strict';

    angular.module('app.core').controller('ManageIndexController', ManageIndexController);

    ManageIndexController.$inject = ['$state'];

    function ManageIndexController($state) {
        $state.go('manage.dashboard');
    }
})();
