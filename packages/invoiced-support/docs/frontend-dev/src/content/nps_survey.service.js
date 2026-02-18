/* globals moment */
(function () {
    'use strict';

    angular.module('app.content').factory('NpsSurvey', NpsSurvey);

    NpsSurvey.$inject = ['$window', 'InvoicedConfig'];

    function NpsSurvey($window, InvoicedConfig) {
        return {
            show: showSurvey,
        };

        function showSurvey(company, user) {
            if ($window._svc || !shouldShowSurvey(company, user)) {
                return;
            }

            if (!$window._sva) {
                $window._sva = {};
            }

            $window._sva.traits = {
                user_id: user.id,
                first_name: user.first_name,
                last_name: user.last_name,
                email: user.email,
                organization: company.name,
                address_one: company.address1,
                address_two: company.address2,
                city: company.city,
                state: company.state,
                zip: company.postal_code,
                country: company.country,
                tenant_id: company.id,
                environment: InvoicedConfig.environment,
                service_level: 'paid',
                registered_on: moment.unix(company.created_at).format('MMM D, YYYY'),
            };

            (function (w) {
                let s = w.document.createElement('script');
                s.src = '//survey.survicate.com/workspaces/3a580c0c27b922658e1bddf01063dcc0/web_surveys.js';
                s.async = true;
                let e = w.document.getElementsByTagName('script')[0];
                e.parentNode.insertBefore(s, e);
            })($window);
        }

        function shouldShowSurvey(company, user) {
            // Only show NPS survey if these conditions are met:
            // i) has been a user for at least three months
            // ii) is a current paying customer
            // iii) we are in production
            // iv) user has not been surveyed in the last year
            //
            // In order to only show the survey to users once per year we show
            // the survey based on the user ID and current month.
            let registeredOn = moment.unix(user.created_at);

            // Do not show survey to financialguide.com users
            if (user.email.indexOf('financialguide.com') !== -1) {
                return false;
            }

            return (
                company.billing.status !== 'trialing' &&
                registeredOn.isBefore(moment().subtract(3, 'month')) &&
                InvoicedConfig.environment === 'production' &&
                company.features.indexOf('not_activated') === -1 &&
                (user.id % 12) + 1 === parseInt(moment().format('M'))
            );
        }
    }
})();
