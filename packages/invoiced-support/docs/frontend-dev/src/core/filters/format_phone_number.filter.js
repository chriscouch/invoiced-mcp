(function () {
    'use strict';

    angular.module('app.core').filter('formatPhoneNumber', formatPhoneNumber);

    // Obtained from https://learnersbucket.com/examples/javascript/how-to-format-phone-number-in-javascript/
    function formatPhoneNumber() {
        return function (str) {
            // Filter only numbers from the input
            let cleaned = ('' + str).replace(/\D/g, '');

            // Check if the input is of correct
            let match = cleaned.match(/^(1|)?(\d{3})(\d{3})(\d{4})$/);

            if (match) {
                // Remove the matched extension code
                // Change this to format for any country code.
                let intlCode = match[1] ? '+1 ' : '';
                return [intlCode, '(', match[2], ') ', match[3], '-', match[4]].join('');
            }

            // No match returns original string
            return str;
        };
    }
})();
