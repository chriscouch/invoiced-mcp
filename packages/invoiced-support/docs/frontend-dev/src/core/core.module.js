(function () {
    'use strict';

    angular.module('app.core', [
        // angular
        'ngResource',
        'ngSanitize',
        'ngCookies',
        // third party
        'ui.router',
        'ui.date',
        'ui.select2',
        'ui.bootstrap',
        'ui.sortable',
        'LocalStorageModule',
        'vButton',
        'pascalprecht.translate',
    ]);
})();
