(function () {
    'use strict';

    angular.module('app.core').filter('escapeHtml', escapeHtml);

    function escapeHtml() {
        let entityMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#x2F;',
            '`': '&#x60;',
            '=': '&#x3D;',
        };

        return function (str) {
            return String(str).replace(/[&<>"'`=\/]/g, function (s) {
                return entityMap[s];
            });
        };
    }
})();
