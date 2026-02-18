(function () {
    'use strict';

    angular.module('app.collections').filter('chasingAction', chasingAction);

    function chasingAction() {
        let actionTypes = {
            email: 'Send an email',
            mail: 'Send a letter',
            phone: 'Phone call',
            sms: 'Send a text message',
            escalate: 'Escalate',
            review: 'Review',
        };

        return function (action) {
            if (typeof actionTypes[action] === 'undefined') {
                return action;
            }

            return actionTypes[action];
        };
    }
})();
