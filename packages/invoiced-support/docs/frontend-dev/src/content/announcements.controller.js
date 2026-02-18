/* globals moment */
(function () {
    'use strict';

    angular.module('app.content').controller('AnnouncementsController', AnnouncementsController);

    AnnouncementsController.$inject = ['$rootScope', '$scope', 'localStorageService', 'selectedCompany', 'Core'];

    function AnnouncementsController($rootScope, $scope, lss, selectedCompany, Core) {
        $scope.company = selectedCompany;
        lss.add('announcementsLastRead', moment().unix());
        $rootScope.notifCount = 0;
        Core.setTitle('Updates');
    }
})();
