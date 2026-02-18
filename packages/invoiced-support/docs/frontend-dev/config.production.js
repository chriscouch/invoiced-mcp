(function () {
    'use strict';

    var InvoicedConfig = {
        activateUrl: 'https://invoiced.com/activate',
        apiBaseUrl: '$API_BASE_URL',
        baseUrl: 'https://invoiced.com',
        csrfCookieName: 'csrf_token',
        defaultPlan: 'invoiced-startup',
        environment: 'production',
        fileUploadUrl: 'https://invoiced-attachments.s3.us-east-2.amazonaws.com/',
        gocardlessDashboardUrl: 'https://manage.gocardless.com',
        heapProjectId: '2367757612',
        ipinfoToken: '8d4d88b59735c1',
        ipinfoUrl: 'https://ipinfo.io/',
        loginUrl: 'https://invoiced.com/login',
        logoutUrl: 'https://invoiced.com/logout',
        paymentsPublishableKey: '413b122c19c6d24b54ed6018d5bc4c4c',
        quickbooksAppUrl: 'https://qbo.intuit.com',
        searchWithAPI: false,
        ssoAcsUrl: 'https://invoiced.com/auth/sso/sp/acs',
        ssoConnectUrl: 'https://invoiced.com/auth/sso/login',
        ssoEntityId: 'invoiced',
        stripeClientId: 'ca_1t8eBk1yavRC8uOqUDc1M8VwBO0EpDZ1',
        stripeDashboardUrl: 'https://dashboard.stripe.com',
        stripePublishableKey: 'pk_live_p02OWJn00niEl6eezNnpQsd0',
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
