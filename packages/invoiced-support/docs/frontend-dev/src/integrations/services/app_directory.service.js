(function () {
    'use strict';

    angular.module('app.integrations').factory('AppDirectory', AppDirectory);

    AppDirectory.$inject = ['Feature', 'InvoicedConfig'];

    function AppDirectory(Feature, InvoicedConfig) {
        return {
            all: getAllApps,
            get: getApp,
        };

        function getAllApps() {
            return [
                {
                    id: 'automations_io',
                    name: 'Automations.io',
                    category: 'Automation',
                    logo: '/img/integrations/automationsio.png',
                    documentationUrl: 'https://automations.io/integrations/invoiced/',
                    explainer:
                        'Integrate Invoiced with your business apps and visually build interactive workflows without writing code, so you can instantly automate manual work.',
                    installation: 'see_documentation',
                    hasFeature: true,
                },
                {
                    id: 'avalara',
                    name: 'Avalara',
                    category: 'Sales Tax',
                    logo: '/img/integrations/avalara.png',
                    documentationUrl: 'https://docs.invoiced.com/integrations/avalara',
                    explainer:
                        'Avalara AvaTax handles tax calculation for you based on the products and services you sell. The integration will add sales tax to invoices, estimates, and credit notes.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    canDisconnect: true,
                },
                {
                    id: 'business_central',
                    name: 'Business Central',
                    category: 'Accounting',
                    logo: '/img/integrations/business_central.svg',
                    documentationUrl: 'https://docs.invoiced.com/accounting/business-central',
                    explainer:
                        'The Business Central integration allows you to sync transactions to and from Microsoft Dynamics 365 Business Central.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                    connectUrl: InvoicedConfig.baseUrl + '/oauth/business_central/start',
                    initialDataSync: [
                        { id: 'business_central_customer', name: 'Customers', type: 'list' },
                        { id: 'business_central_invoice', name: 'Invoices', type: 'transaction' },
                        { id: 'business_central_credit_note', name: 'Credit Memos', type: 'transaction' },
                    ],
                },
                {
                    id: 'chartmogul',
                    name: 'ChartMogul',
                    category: 'Business Intelligence',
                    logo: '/img/integrations/chartmogul.png',
                    documentationUrl: 'https://docs.invoiced.com/integrations/chartmogul',
                    explainer:
                        "Add subscription analytics to Invoiced with the ChartMogul integration. ChartMogul is the world's first subscription data platform that provides valuable insights into your billing data.",
                    installation: 'in_app',
                    hasConfiguration: true,
                    canDisconnect: true,
                },
                {
                    id: 'chartmogul_by_saasync',
                    name: 'ChartMogul by SaaSync',
                    category: 'Business Intelligence',
                    logo: '/img/integrations/chartmogul.png',
                    documentationUrl:
                        'https://support.saasync.com/hc/en-us/articles/23479262039451-Getting-Started-and-Authenticating-with-Invoiced',
                    explainer:
                        "Add subscription analytics to Invoiced with the ChartMogul integration. ChartMogul is the world's first subscription data platform that provides valuable insights into your billing data.",
                    installation: 'see_documentation',
                    hasFeature: true,
                },
                {
                    id: 'collbox',
                    name: 'Collbox',
                    category: 'Debt Collection',
                    logo: '/img/integrations/collbox.png',
                    documentationUrl: 'https://collbox.co/invoiced/',
                    explainer:
                        'Collbox allows you to send past due accounts to a collections agency with the press of a button. You only pay when the collections agency successfully collects for you.',
                    installation: 'see_documentation',
                    hasFeature: true,
                },
                {
                    id: 'earth_class_mail',
                    name: 'Earth Class Mail',
                    category: 'Check Lockbox',
                    logo: '/img/integrations/earth_class_mail.png',
                    documentationUrl: 'https://docs.invoiced.com/integrations/earth-class-mail',
                    explainer:
                        "Earth Class Mail's CheckStream product gives you a physical address where customers can send checks. As checks are received they will be automatically opened, scanned, deposited, and added to Invoiced as a customer payment",
                    installation: 'in_app',
                    hasConfiguration: true,
                    canDisconnect: true,
                },
                {
                    id: 'erp_connect',
                    name: 'ERP Connect',
                    logo: '/img/integrations/invoiced.png',
                    category: 'Accounting',
                    documentationUrl: 'https://docs.invoiced.com/accounting/erp-connect',
                    explainer: 'ERP Connect allows you to securely integrate your on-premise ERP system with Invoiced.',
                    installation: 'see_documentation',
                    hasFeature: true,
                },
                {
                    id: 'freshbooks',
                    name: 'FreshBooks',
                    category: 'Accounting',
                    logo: '/img/integrations/freshbooks.png',
                    documentationUrl: 'https://docs.invoiced.com/accounting/freshbooks',
                    explainer: 'The FreshBooks integration allows you to sync transactions to and from FreshBooks.',
                    installation: 'in_app',
                    hasConfiguration: false,
                    isAccountingSync: true,
                    canDisconnect: true,
                    connectUrl: InvoicedConfig.baseUrl + '/oauth/freshbooks/start',
                    initialDataSync: [
                        { id: 'freshbooks_customer', name: 'Clients', type: 'list' },
                        { id: 'freshbooks_invoice', name: 'Invoices', type: 'transaction' },
                    ],
                },
                {
                    id: 'lob',
                    name: 'Lob',
                    logo: '/img/integrations/lob.png',
                    category: 'Direct Mail',
                    documentationUrl: 'https://docs.invoiced.com/integrations/lob',
                    explainer:
                        "Our Lob integration enables you to send paper invoices and statements to customers via direct mail. The integration will physically deliver a letter to your client's doorstep. It will use the same invoice or statement template they would see if they downloaded the invoice. Letters are perfect for another way to get in front of your client.",
                    installation: 'in_app',
                    hasConfiguration: true,
                    canDisconnect: true,
                },
                {
                    id: 'netsuite',
                    name: 'NetSuite',
                    logo: '/img/integrations/netsuite.png',
                    category: 'Accounting',
                    documentationUrl: 'https://docs.invoiced.com/accounting/netsuite',
                    explainer:
                        'The NetSuite integration allows you to sync transactions to and from your NetSuite ERP.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                },
                {
                    id: 'quickbooks_desktop',
                    name: 'QuickBooks Desktop',
                    logo: '/img/integrations/quickbooks.svg',
                    category: 'Accounting',
                    documentationUrl: 'https://docs.invoiced.com/accounting/quickbooks-desktop',
                    explainer:
                        'The QuickBooks Desktop integration allows you to sync transactions to and from your QuickBooks Desktop company file.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                },
                {
                    id: 'quickbooks_online',
                    name: 'QuickBooks Online',
                    logo: '/img/integrations/quickbooks.svg',
                    category: 'Accounting',
                    documentationUrl: 'https://docs.invoiced.com/accounting/quickbooks-online',
                    explainer:
                        'The QuickBooks Online integration allows you to sync transactions to and from your QuickBooks Online company.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                    connectUrl: InvoicedConfig.baseUrl + '/oauth/quickbooks_online/start',
                    initialDataSync: [
                        { id: 'quickbooks_online_customer', name: 'Customers', type: 'list' },
                        { id: 'quickbooks_online_item', name: 'Products and Services', type: 'list' },
                        { id: 'quickbooks_online_invoice', name: 'Invoices', type: 'transaction' },
                        { id: 'quickbooks_online_credit_note', name: 'Credit Memos', type: 'transaction' },
                        { id: 'quickbooks_online_payment', name: 'Payments', type: 'transaction' },
                    ],
                },
                {
                    id: 'sage_accounting',
                    name: 'Sage Accounting',
                    category: 'Accounting',
                    logo: '/img/integrations/sage_accounting.png',
                    explainer:
                        'The Sage Accounting integration allows you to sync transactions to and from Sage Accounting.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                    connectUrl: InvoicedConfig.baseUrl + '/oauth/sage_accounting/start',
                    initialDataSync: [
                        { id: 'sage_accounting_customer', name: 'Customers', type: 'list' },
                        { id: 'sage_accounting_invoice', name: 'Invoices', type: 'transaction' },
                        { id: 'sage_accounting_credit_note', name: 'Credit Notes', type: 'transaction' },
                    ],
                },
                {
                    id: 'intacct',
                    name: 'Sage Intacct',
                    logo: '/img/integrations/intacct.png',
                    category: 'Accounting',
                    documentationUrl: 'https://docs.invoiced.com/accounting/intacct',
                    explainer: 'The Intacct integration allows you to sync transactions to and from your Intacct ERP.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                    initialDataSync: [
                        { id: 'intacct_customer', name: 'Customers', type: 'list' },
                        { id: 'intacct_order_entry_invoice', name: 'Order Entry Invoices', type: 'transaction' },
                        { id: 'intacct_order_entry_return', name: 'Order Entry Returns', type: 'transaction' },
                        { id: 'intacct_ar_adjustment', name: 'A/R Adjustments', type: 'transaction' },
                        { id: 'intacct_ar_payment', name: 'Payments', type: 'transaction' },
                    ],
                },
                {
                    id: 'salesforce',
                    name: 'Salesforce',
                    logo: '/img/integrations/salesforce.png',
                    category: 'CRM',
                    documentationUrl: 'https://docs.invoiced.com/integrations/salesforce',
                    installUrl:
                        'https://appexchange.salesforce.com/appxListingDetail?listingId=a0N3A00000G0yPTUAZ&tab=e',
                    explainer:
                        'The Invoiced for Salesforce app adds accounts receivable automation capabilities to Salesforce. Installing the native Salesforce app allows you to sync Invoiced billing data with Salesforce.',
                    installation: 'see_documentation',
                    hasFeature: Feature.hasFeature('salesforce'),
                },
                {
                    id: 'slack',
                    name: 'Slack',
                    logo: '/img/integrations/slack.png',
                    category: 'Communication',
                    documentationUrl: 'https://docs.invoiced.com/integrations/slack',
                    explainer:
                        'Receive real-time notifications on Slack when important billing events occur. The Slack integration can notify you when a new customer is added or an invoice is paid.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    canDisconnect: true,
                    connectUrl: InvoicedConfig.baseUrl + '/oauth/slack/start',
                },
                {
                    id: 'stitch',
                    name: 'Stitch',
                    logo: '/img/integrations/stitch.png',
                    category: 'Business Intelligence',
                    documentationUrl: 'https://www.stitchdata.com/docs/integrations/saas/invoiced',
                    explainer:
                        'Stitch allows you to send your Invoiced data to your data warehouse or database for use in business intelligence workflows.',
                    installation: 'see_documentation',
                    hasFeature: true,
                },
                {
                    id: 'twilio',
                    name: 'Twilio',
                    logo: '/img/integrations/twilio.png',
                    category: 'SMS',
                    documentationUrl: 'https://docs.invoiced.com/integrations/twilio',
                    explainer: 'With Twilio you can send invoices and statements to customers via text messages.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    canDisconnect: true,
                },
                {
                    id: 'xero',
                    name: 'Xero',
                    logo: '/img/integrations/xero.png',
                    category: 'Accounting',
                    documentationUrl: 'https://docs.invoiced.com/accounting/xero',
                    explainer: 'The Xero integration allows you to sync transactions to and from your Xero account.',
                    installation: 'in_app',
                    hasConfiguration: true,
                    isAccountingSync: true,
                    canDisconnect: true,
                    connectUrl: InvoicedConfig.baseUrl + '/oauth/xero/start',
                    initialDataSync: [
                        { id: 'xero_contact', name: 'Contacts', type: 'list' },
                        { id: 'xero_invoice', name: 'Invoices', type: 'transaction' },
                        { id: 'xero_credit_note', name: 'Credit Memos', type: 'transaction' },
                        { id: 'xero_payment', name: 'Payments', type: 'transaction' },
                        { id: 'xero_batch_payment', name: 'Batch Payments', type: 'transaction' },
                    ],
                },
                {
                    id: 'zapier',
                    name: 'Zapier',
                    logo: '/img/integrations/zapier.png',
                    category: 'Automation',
                    documentationUrl: 'https://docs.invoiced.com/integrations/zapier',
                    explainer:
                        'Zapier allows you to easily build and automate workflows between Invoiced and more than 1,500 other cloud apps.',
                    installation: 'see_documentation',
                    hasFeature: true,
                },
            ];
        }

        function getApp(id) {
            let allApps = getAllApps();
            for (let i in allApps) {
                if (allApps[i].id === id) {
                    return allApps[i];
                }
            }

            return null;
        }
    }
})();
