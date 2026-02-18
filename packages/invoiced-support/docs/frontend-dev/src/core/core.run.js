(function () {
    'use strict';

    angular.module('app.core').run(runBlock);

    runBlock.$inject = ['$rootScope', '$state', 'Core'];

    function runBlock($rootScope, $state, Core) {
        $rootScope.$on('$stateChangeSuccess', function (event, toState) {
            $('html').removeClass('gray-bg');

            if (toState.name !== 'manage.index') {
                Core.closeLoadingScreen();
            }
        });

        $rootScope.$on('$stateChangeError', function () {
            $state.go('index');
        });
    }
})();
