/* globals moment */
(function () {
    'use strict';

    angular.module('app.content').factory('HeapAnalytics', HeapAnalytics);

    HeapAnalytics.$inject = ['$window', 'InvoicedConfig'];

    function HeapAnalytics($window, InvoicedConfig) {
        return {
            selectMember: selectMember,
        };

        function selectMember(company, user, member) {
            if (typeof $window.heap === 'undefined') {
                return;
            }

            $window.heap.identify(member.id + '');
            $window.heap.addUserProperties({
                'Given Name': user.first_name,
                'Family Name': user.last_name,
                Name: (user.first_name + ' ' + user.last_name).trim(),
                Email: user.email,
                'Invoiced User ID': user.id,
                'User Created At': moment.unix(user.created_at).format('YYYY-MM-DD'),
                Environment: InvoicedConfig.environment,
                'Tenant ID': company.id,
                'Company Name': company.name,
                'Company Address1': company.address1,
                'Company Address2': company.address2,
                'Company City': company.city,
                'Company State': company.state,
                'Company Zip': company.postal_code,
                'Company Country': company.country,
                'Company Currency': company.currency,
                'Company Created At': moment.unix(company.created_at).format('YYYY-MM-DD'),
                Industry: company.industry,
                Website: company.website,
                Phone: company.phone,
                Role: member.role,
            });
        }
    }
})();
