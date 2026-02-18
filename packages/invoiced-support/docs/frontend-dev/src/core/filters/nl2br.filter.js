(function () {
    'use strict';

    angular.module('app.core').filter('nl2br', nl2br);

    function nl2br() {
        return function (input) {
            if (typeof input !== 'undefined' && input) {
                input = input.toString();
                if (input.indexOf('<br') !== -1) {
                    return input;
                }

                // NOTE: &#10; is the line feed HTML entity
                // that is output when nl2br is used with the
                // linky filter

                return input.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n|&#10;)/g, '$1<br />$2');
            } else {
                return '';
            }
        };
    }
})();
