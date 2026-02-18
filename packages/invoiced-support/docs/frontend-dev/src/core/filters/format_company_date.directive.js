(function () {
    'use strict';

    angular.module('app.core').filter('formatCompanyDate', formatCompanyDate);

    formatCompanyDate.$inject = ['$filter', 'selectedCompany'];

    function formatCompanyDate($filter, selectedCompany) {
        return function (date) {
            return $filter('formatDate')(date, selectedCompany.date_format);
        };
    }
})();
