(function () {
    'use strict';

    angular.module('app.core').factory('CSRF', CSRF);

    CSRF.$inject = ['CurrentUser', '$timeout', '$log'];

    function CSRF(CurrentUser, $timeout, $log) {
        return function wrap(cb) {
            // Load an innocuous endpoint to ensure
            // that we have a CSRF token. There are several
            // scenarios in which the user might not have a CSRF
            // token. Like if they hit the logout endpoint directly
            // or if there are multiple asynchronous calls.
            CurrentUser.ping(
                function csrfPingCallback() {
                    // We add a timeout here because angular.js v1.3 only
                    // updates the cookie store by polling, every 100ms.
                    // If the cookie store has not been updated then the
                    // call could be using an older, and invalid, CSRF token.
                    // This was fixed in angular.js v1.4 The workaround until
                    // we can upgrade to v1.4 is to add a timeout that's
                    // gives the cookie store time to load the latest cookies.
                    $timeout(function csrfPingTimeout() {
                        cb();
                    }, 200);
                },
                function csrfPingError() {
                    // something went wrong if we reached here...
                    $log.error('Failed to load a CSRF token');
                },
            );
        };
    }
})();
