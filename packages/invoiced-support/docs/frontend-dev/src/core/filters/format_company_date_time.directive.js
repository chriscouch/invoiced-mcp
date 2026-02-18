(function () {
    'use strict';

    angular.module('app.core').filter('formatCompanyDateTime', formatCompanyDateTime);

    formatCompanyDateTime.$inject = ['$filter', 'selectedCompany'];

    function formatCompanyDateTime($filter, selectedCompany) {
        return function (date) {
            let dateFormat = selectedCompany.date_format;
            if (dateFormat) {
                dateFormat += ' g:i a';
            } else {
                dateFormat = 'M j, Y, g:i a';
            }

            return $filter('formatDate')(date, dateFormat);
        };
    }
})();
