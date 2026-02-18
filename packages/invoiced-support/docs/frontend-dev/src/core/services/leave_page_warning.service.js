(function () {
    'use strict';

    angular.module('app.core').factory('LeavePageWarning', LeavePageWarning);

    LeavePageWarning.$inject = ['$window', '$rootScope'];

    function LeavePageWarning($window, $rootScope) {
        let LPW = {
            // pushes a blocker on to the stack
            block: function () {
                _blockers++;

                return this;
            },

            // pops a blocker from the stack
            unblock: function () {
                _blockers--;
                _blockers = Math.max(0, _blockers);

                return this;
            },

            // checks if the page can be left
            canLeave: function () {
                return _blockers === 0;
            },

            // toggles the leave page warning based on whether a form has changed data
            watchForm: function ($scope, formName) {
                let watcherInitialized = false;
                $scope.$watch(formName + '.$dirty', function (dirty) {
                    if (!watcherInitialized) {
                        watcherInitialized = true;
                        return;
                    }

                    if (dirty) {
                        LPW.block();
                    } else {
                        LPW.unblock();
                    }
                });

                return this;
            },
        };

        let _blockers = 0;
        let leaveMessage = 'Are you sure you want to leave?';

        // called whenever an in-app state/route change happens
        let askToLeaveSate = function (event) {
            warnOfLeave();

            if (LPW.canLeave()) {
                return;
            }

            if (!$window.confirm(leaveMessage)) {
                event.preventDefault();
            } else {
                _blockers = 0;
            }
        };

        // called whenever user attempts to leave the app
        let askToLeaveWindow = function (event) {
            warnOfLeave();

            if (!LPW.canLeave()) {
                event.returnValue = leaveMessage;
                return leaveMessage;
            }
        };

        // broadcasts an event that the user might be leaving
        let warnOfLeave = function () {
            $rootScope.$broadcast('leavePageWarning');
        };

        // ask before leaving the state (ui-router)
        $rootScope.$on('$stateChangeStart', askToLeaveSate);

        // ask before leaving the route (ngRoute)
        $rootScope.$on('$routeChangeStart', askToLeaveSate);

        // ask before leaving the app
        $($window).off('beforeunload').bind('beforeunload', askToLeaveWindow);

        return LPW;
    }
})();
