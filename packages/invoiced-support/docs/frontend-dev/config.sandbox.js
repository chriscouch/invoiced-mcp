(function () {
    'use strict';

    var InvoicedConfig = {
        activateUrl: 'https://sandbox.invoiced.com/activate',
        apiBaseUrl: '$API_BASE_URL',
        baseUrl: 'https://sandbox.invoiced.com',
        csrfCookieName: 'csrf_sandbox_token',
        defaultPlan: 'invoiced-startup',
        environment: 'sandbox',
        fileUploadUrl: 'https://invoiced-attachments.s3.us-east-2.amazonaws.com/',
        gocardlessDashboardUrl: 'https://manage-sandbox.gocardless.com',
        heapProjectId: '2905373259',
        ipinfoToken: '8d4d88b59735c1',
        ipinfoUrl: 'https://ipinfo.io/',
        loginUrl: 'https://sandbox.invoiced.com/login',
        logoutUrl: 'https://sandbox.invoiced.com/logout',
        paymentsPublishableKey: 'fe027c1a460aa20ed9394d9a3c268fd2',
        quickbooksAppUrl: 'https://sandbox.qbo.intuit.com',
        searchWithAPI: false,
        ssoAcsUrl: 'https://sandbox.invoiced.com/auth/sso/sp/acs',
        ssoConnectUrl: 'https://sandbox.invoiced.com/auth/sso/login',
        ssoEntityId: 'invoiced',
        stripeClientId: 'ca_1t8eBk1yavRC8uOqUDc1M8VwBO0EpDZ1',
        stripeDashboardUrl: 'https://dashboard.stripe.com/test',
        stripePublishableKey: 'pk_test_mEVkj3oEd2kjNtEMb7V0qELD',
        upgradeUrl: 'https://www.invoiced.com/upgrade',
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
