<?php

namespace App\Companies\Libs;

use App\Companies\Models\Company;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Exception\ModelException;

/**
 * Deletes a company by removing all of its data in the database.
 */
class DeleteCompany
{
    private const DELETE_CHUNK = 100000;
    private const TABLES = [
        'AccountingConvenienceFeeMappings',
        'AccountingCreditNoteMappings',
        'AccountingCustomerMappings',
        'AccountingInvoiceMappings',
        'AccountingPaymentMappings',
        'AccountingSyncStatuses',
        'AccountingTransactionMappings',
        'AccountsPayableSettings',
        'AccountsReceivableSettings',
        'ApiKeys',
        'AppliedRates',
        'Attachments',
        'AutoNumberSequences',
        'AutomationRuns',
        'AutomationStepRuns',
        'AutomationWorkflowSteps',
        'AutomationWorkflowTriggers',
        'AutomationWorkflowVersions',
        'AutomationWorkflows',
        'AvalaraAccounts',
        'BankAccounts',
        'BillApprovals',
        'BillAttachments',
        'BillRejections',
        'BilledVolumes',
        'Bundles',
        'Cards',
        'CashApplicationBankAccounts',
        'CashApplicationRules',
        'CashApplicationSettings',
        'CatalogItems',
        'ChartMogulAccounts',
        'ChasingStatistics',
        'Comments',
        'CompanyAddresses',
        'CompanyEmailAddresses',
        'CompanyTaxIds',
        'CompletedChasingSteps',
        'Contacts',
        'CouponRedemptions',
        'Coupons',
        'CreditBalances',
        'CspTrustedSites',
        'CustomFields',
        'CustomerPortalEvents',
        'CustomerPortalSettings',
        'CustomerVolumes',
        'Dashboards',
        'DisabledPaymentMethods',
        'DocumentViews',
        'EChecks',
        'EarthClassMailAccounts',
        'EmailParticipants',
        'EmailTemplateOptions',
        'EmailTemplates',
        'EmailThreadNotes',
        'Emails',
        'Events',
        'ExpectedPaymentDates',
        'Exports',
        'Features',
        'Filters',
        'FlywirePayouts',
        'FlywireDisbursements',
        'FlywireRefunds',
        'FlywireRefundBundles',
        'FlywirePayments',
        'GlAccounts',
        'ImportedObjects',
        'InboxEmails',
        'InitiatedChargeDocuments',
        'InstalledProducts',
        'IntacctAccounts',
        'IntacctSyncProfiles',
        'InvoiceChasingCadences',
        'InvoiceDeliveries',
        'InvoiceDistributions',
        'InvoiceTemplates',
        'InvoiceVolumes',
        'LateFees',
        'Letters',
        'LobAccounts',
        'MarketingAttributions',
        'MerchantAccountRoutings',
        'MerchantAccountTransactionNotifications',
        'MerchantAccountTransactions',
        'Metadata',
        'MrrItems',
        'MrrMovements',
        'MrrVersions',
        'NetSuiteAccounts',
        'NetworkQueuedSends',
        'Notes',
        'NotificationEventCompanySettings',
        'NotificationEventSettings',
        'NotificationRecipients',
        'NotificationSubscriptions',
        'Notifications',
        'OverageCharges',
        'PaymentInstructions',
        'PaymentFlows',
        'PaymentLinkSessions',
        'PaymentLinkItems',
        'PaymentLinkFields',
        'PaymentLinks',
        'PaymentMethods',
        'Payouts',
        'PaymentPlanInstallments',
        'PaymentTerms',
        'PlaidBankAccountLinks',
        'Plans',
        'ProductPricingPlans',
        'QuickBooksAccounts',
        'QuickBooksOnlineSyncProfiles',
        'Quotas',
        'ReconciliationErrors',
        'Refunds',
        'Reports',
        'Roles',
        'SavedReports',
        'ScheduledReports',
        'ScheduledSends',
        'ShippingDetails',
        'ShippingRates',
        'SignUpPageAddons',
        'SlackAccounts',
        'SmtpAccounts',
        'StripeAccounts',
        'StripeCustomers',
        'SubscriptionAddons',
        'SubscriptionBillingSettings',
        'Tasks',
        'TaxRates',
        'TaxRules',
        'Templates',
        'TextMessages',
        'Themes',
        'TokenizationFlows',
        'TwilioAccounts',
        'UsagePricingPlans',
        'VendorAdjustments',
        'VendorCreditApprovals',
        'VendorCreditRejections',
        'VendorCreditAttachments',
        'VendorPaymentAttachments',
        'VendorPaymentItems',
        'WePayData',
        'WebhookAttempts',
        'Webhooks',
        'XeroAccounts',
        'XeroSyncProfiles',

        // delete after AccountingTransactionMappings and Late Fees
        'Transactions',

        // delete after refunds
        'CustomerPaymentBatchItems',
        'CustomerPaymentBatches',
        'Charges',

        // delete after inbox emails and email thread notes
        'EmailThreads',

        // delete after imported objects
        'Imports',

        // delete after scheduled reports
        'SavedReports',

        // delete after Notification Recepients
        'NotificationEvents',

        // delete after themes
        'PdfTemplates',

        // delete after attachments
        'Files',

        // delete after emails
        'EmailOpens',

        // delete after Initiated charge documents
        'InitiatedCharges',

        // delete after applied rates and late fees
        'LineItems',

        // delete after transactions and multiple tables
        'Payments',

        // delete after multiple tables
        'VendorPaymentBatchBills',
        'VendorPayments',
        'VendorPaymentBatchChecks',
        'VendorPaymentBatches',
        'CompanyBankAccounts',
        'CompanyCards',

        // delete after multiple tables
        'Members',
        'PaymentAttributes',
        'CreditNotes',
        'Estimates',
        'EstimateApprovals',
        // delete after multiple tables and after subscriptions addons and shipping details
        'Subscriptions',
        'SubscriptionApprovals',

        // delete after Payments
        'PaymentPlanApprovals',
        'PaymentPlans',

        // delete after multiple tables
        'Invoices',
        'Bills',
        'VendorCredits',

        // delete after multiple tables
        'Inboxes',

        // delete after bills and vendor credits
        'ApprovalWorkflowPaths',
        'ApprovalWorkflows',
        'ApprovalWorkflowSteps',

        // delete after multiple tables
        'Customers',
        'Vendors',

        // delete after Customers
        'MerchantAccounts',
        'LateFeeSchedules',
        'SignUpPages',
        'ChasingCadenceSteps',

        // delete after ChasingCadenceSteps
        'ContactRoles',
        'ChasingCadences',
        'SmsTemplates',
    ];

    // "EmailParticipantAssociations",

    public function __construct(private Connection $database)
    {
    }

    /**
     * @throws ModelException
     */
    public function delete(Company $company): void
    {
        // special cases
        $this->runQueryInLoop("DELETE FROM CompanySamlSettings WHERE company_id = {$company->id} LIMIT ".self::DELETE_CHUNK);
        // clean up network connections
        $this->runQueryInLoop("DELETE FROM NetworkConnections WHERE vendor_id = {$company->id} LIMIT ".self::DELETE_CHUNK);
        $this->runQueryInLoop("DELETE FROM NetworkConnections WHERE customer_id = {$company->id} LIMIT ".self::DELETE_CHUNK);

        foreach (self::TABLES as $table) {
            $this->runQueryInLoop("DELETE FROM {$table} WHERE tenant_id = {$company->id} LIMIT ".self::DELETE_CHUNK);
        }

        $company->deleteOrFail();
    }

    private function runQueryInLoop(string $sql): void
    {
        while ($this->database->executeStatement($sql)) {
        }
    }
}
