/* jshint -W117, -W030 */
describe('billing portal', function () {
    'use strict';

    let BillingPortal;
    let selectedCompany;
    let key = 'bae36435a15fc8b8a0404b8223227be8fbae36435a15fc8b8a0404b8223227be8';

    beforeEach(function () {
        module('app.accounts_receivable');

        inject(function (_BillingPortal_, _selectedCompany_) {
            BillingPortal = _BillingPortal_;
            selectedCompany = _selectedCompany_;

            angular.extend(selectedCompany, {
                id: 10,
                url: 'https://example.invoiced.com',
                sso_key: key,
            });
        });
    });

    describe('generateLoginToken', function () {
        it('should correctly generate a sign in token', function () {
            let customer = {
                id: 100,
            };

            let link = BillingPortal.generateLoginToken(customer);

            let isValid = KJUR.jws.JWS.verifyJWT(link, key, {
                alg: ['HS256'],
                iss: 10,
                sub: 100,
            });
            expect(isValid).toBe(true);

            customer.id = 101;
            let link2 = BillingPortal.generateLoginToken(customer);

            expect(link2).not.toEqual(link);
        });
    });

    describe('loginUrl', function () {
        it('should correctly generate a sign in link', function () {
            let link = BillingPortal.loginUrl('tok_test');
            expect(link).toEqual('https://example.invoiced.com/login/tok_test');

            let link2 = BillingPortal.loginUrl();
            expect(link2).toEqual('https://example.invoiced.com/login');
        });
    });
});
