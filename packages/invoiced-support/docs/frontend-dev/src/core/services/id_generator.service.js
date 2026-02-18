(function () {
    'use strict';

    angular.module('app.core').factory('IdGenerator', IdGenerator);

    function IdGenerator() {
        let service = {
            generate: generate,
        };

        return service;

        function generate(name, spaceChar) {
            spaceChar = spaceChar || '-';

            let str = name
                .toLowerCase()
                .trim()
                // only allow a-z, A-Z, 0-9 _, -
                // (spaces included because they are converted to '-')
                .replace(/[^a-z0-9_\s-]/g, '')
                // convert spaces to hyphens
                .replace(/\s/g, spaceChar)
                // prevent runs of multiple hyphens
                .replace(new RegExp(spaceChar + spaceChar + '+', 'g'), spaceChar);

            // first character cannot be '-'
            if (str.substring(0, 1) == spaceChar) {
                str = str.substring(1);
            }
            // last character cannot be '-'
            if (str.substring(str.length - 1, str.length) == spaceChar) {
                str = str.substring(0, str.length - 1);
            }

            return str;
        }
    }
})();
