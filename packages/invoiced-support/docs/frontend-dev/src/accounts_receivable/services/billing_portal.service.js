/* globals JSON, KJUR, moment */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('BillingPortal', BillingPortal);

    BillingPortal.$inject = ['selectedCompany'];

    function BillingPortal(selectedCompany) {
        let algo = 'HS256';
        let ttl = {
            days: 7,
        };

        return {
            loginUrl: loginUrl,
            generateLoginToken: generateLoginToken,
        };

        function loginUrl(token) {
            return selectedCompany.url + '/login' + (token ? '/' + token : '');
        }

        function generateLoginToken(customer) {
            let header = {
                typ: 'JWT',
                alg: algo,
            };
            header = JSON.stringify(header);

            // build payload
            let now = moment().subtract(5, 'minutes').unix(); // set in the past to handle user clock being offset from server
            let end = moment().add(ttl).unix();
            let params = {
                iss: selectedCompany.id,
                sub: customer.id,
                iat: now,
                exp: end,
            };
            let payload = JSON.stringify(params);

            // sign JWT
            return KJUR.jws.JWS.sign(algo, header, payload, selectedCompany.sso_key);
        }
    }
})();
