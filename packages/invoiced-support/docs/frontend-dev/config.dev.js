(function () {
    'use strict';

    var baseUrl = 'http://invoiced.localhost:1234';

    var InvoicedConfig = {
        // test connection failure
        // apiBaseUrl: 'http://domaindoesnotexistandwilltimeout.com',
        // baseUrl: 'http://domaindoesnotexistandwilltimeout.com',

        activateUrl: baseUrl + '/activate',
        apiBaseUrl: 'http://app.invoiced.localhost:1236/api',
        baseUrl: baseUrl,
        csrfCookieName: 'csrf_dev_token',
        environment: 'dev',
        fileUploadUrl: 'https://invoiced-attachments.s3.us-east-2.amazonaws.com/',
        gocardlessDashboardUrl: 'https://manage-sandbox.gocardless.com',
        heapProjectId: '2905373259',
        ipinfoToken: '8d4d88b59735c1',
        ipinfoUrl: 'http://ipinfo.io/',
        paymentsPublishableKey: '383d7c8af35fb7199a09a096d963e5ec',
        quickbooksAppUrl: 'https://sandbox.qbo.intuit.com',
        searchWithAPI: true,
        ssoAcsUrl: baseUrl + '/auth/sso/sp/acs',
        ssoConnectUrl: baseUrl + '/auth/sso/login',
        ssoEntityId: 'invoiced',
        stripeClientId: 'ca_1t8e7QhQHzsytwLAe6xKivcP591wVU7u',
        stripeDashboardUrl: 'https://dashboard.stripe.com/test',
        stripePublishableKey: 'pk_test_mEVkj3oEd2kjNtEMb7V0qELD',
        upgradeUrl: baseUrl + '/upgrade',
    };

    if (typeof exports !== 'undefined') {
        if (typeof module !== 'undefined' && module.exports) {
            exports = module.exports = InvoicedConfig;
        }
        exports.InvoicedConfig = InvoicedConfig;
    } else {
        this.InvoicedConfig = InvoicedConfig;
    }
}).call(this);
