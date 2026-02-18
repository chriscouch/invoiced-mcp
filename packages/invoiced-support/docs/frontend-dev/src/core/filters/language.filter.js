(function () {
    'use strict';

    angular.module('app.core').filter('language', language);

    language.$inject = ['InvoicedConfig'];

    function language(InvoicedConfig) {
        return function (language) {
            if (typeof language === 'undefined' || !language) {
                return '';
            }

            for (let i in InvoicedConfig.languages) {
                if (InvoicedConfig.languages[i].code === language) {
                    return InvoicedConfig.languages[i].language;
                }
            }

            return '';
        };
    }
})();
