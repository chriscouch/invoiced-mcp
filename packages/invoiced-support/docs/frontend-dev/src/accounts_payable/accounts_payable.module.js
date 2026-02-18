(function () {
    'use strict';

    angular.module('app.accounts_payable', ['app.core', 'signature']).config(function ($provide) {
        //decorates angularJS 1.3 $timeout service
        //to be compatible with 1.8
        $provide.decorator('$timeout', function ($delegate) {
            let original = function (fn, delay, invokeApply) {
                if (!fn) {
                    fn = function () {};
                }

                // Call the original $timeout function.
                return $delegate(fn, delay, invokeApply);
            };
            original.cancel = $delegate.cancel;

            return original;
        });
    });
})();
