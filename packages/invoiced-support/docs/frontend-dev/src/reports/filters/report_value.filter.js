(function () {
    'use strict';

    angular.module('app.reports').filter('reportValue', reportValue);

    reportValue.$inject = ['$filter'];

    function reportValue($filter) {
        let escapeHtml = $filter('escapeHtml');

        return function (value) {
            if (typeof value === 'undefined' || !value) {
                return '';
            } else if (typeof value === 'object') {
                let output;
                if (typeof value.formatted !== 'undefined') {
                    output = value.formatted;
                } else if (typeof value.name !== 'undefined') {
                    output = value.name;
                } else {
                    output = value.value;
                }

                output = escapeHtml(output);

                if (typeof value.credit_note !== 'undefined') {
                    return '<a href="/credit_notes/' + value.credit_note + '">' + output + '</a>';
                } else if (typeof value.customer !== 'undefined') {
                    return '<a href="/customers/' + value.customer + '">' + output + '</a>';
                } else if (typeof value.estimate !== 'undefined') {
                    return '<a href="/estimates/' + value.estimate + '">' + output + '</a>';
                } else if (typeof value.invoice !== 'undefined') {
                    return '<a href="/invoices/' + value.invoice + '">' + output + '</a>';
                } else if (typeof value.payment !== 'undefined') {
                    return '<a href="/payments/' + value.payment + '">' + output + '</a>';
                } else if (typeof value.subscription !== 'undefined') {
                    return '<a href="/subscriptions/' + value.subscription + '">' + output + '</a>';
                }

                return output;
            }

            return escapeHtml(value);
        };
    }
})();
