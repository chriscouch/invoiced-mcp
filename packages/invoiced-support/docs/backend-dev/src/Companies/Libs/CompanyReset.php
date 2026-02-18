<?php

namespace App\Companies\Libs;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\Companies\Models\Company;
use App\Core\Ledger\Ledger;
use App\Core\Search\Libs\SearchReset;
use App\Core\Database\DatabaseHelper;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Clears the data for a company in test mode.
 */
class CompanyReset
{
    private const DATA_TABLES = [
        'all' => [
            'AutomationRuns',
            'EmailParticipants',
            'Emails',
            'EmailThreads',
            'InboxEmails',
            'Events',
            'Files',
            'Imports',
            'ReconciliationErrors',
            'WebhookAttempts',
        ],
        'accounts_receivable' => [
            'AdyenAffirmCaptures',
            'AppliedRates',
            'Attachments',
            'Comments',
            'Contacts',
            'CreditNotes',
            'PaymentLinkSessions',
            'PaymentLinkItems',
            'PaymentFlows',
            'FlywirePayouts',
            'FlywireDisbursements',
            'FlywireRefunds',
            'FlywireRefundBundles',
            'FlywirePayments',
            'TokenizationFlows',
            'Customers',
            'Estimates',
            'Invoices',
            'LineItems',
            'Transactions',
            'Payouts',
            'MerchantAccountTransactionNotifications',
            'MerchantAccountTransactions',
            'Charges',
            'Refunds',
            'Payments',
            'PaymentLinkSessions',
            'PaymentLinkItems',
            'PaymentLinks',
        ],
        'accounts_payable' => [
            'VendorPaymentBatches',
            'BillApprovals',
            'BillRejections',
            'VendorCreditApprovals',
            'VendorCreditRejections',
            'VendorPaymentItems',
            'VendorPayments',
            'Bills',
            'VendorCredits',
            'VendorAdjustments',
            'Vendors',
        ],
        'subscription_billing' => [
            'Subscriptions',
        ],
    ];

    private const SETTINGS_TABLES = [
        'all' => [
            'CustomFields',
            'GlAccounts',
            'AutomationWorkflows',
        ],
        'accounts_receivable' => [
            'Bundles',
            'CatalogItems',
            'ChasingCadences',
            'Coupons',
            'CspTrustedSites',
            'EmailTemplates',
            'EmailTemplateOptions',
            'InvoiceChasingCadences',
            'LateFeeSchedules',
            'PdfTemplates',
            'SignUpPages',
            'SmsTemplates',
            'TaxRates',
            'TaxRules',
            'Themes',
        ],
        'accounts_payable' => [
            'ApprovalWorkflowSteps',
            'ApprovalWorkflowPaths',
            'ApprovalWorkflows',
        ],
        'subscription_billing' => [
            'Plans',
        ],
    ];

    // tables that can use big delete with keys non-equal to id
    private const NON_ID_TABLES = [
        'Attachments' => 'parent_id',
        'CustomFields' => 'internal_id',
        'CatalogItems' => 'internal_id',
        'Coupons' => 'internal_id',
        'TaxRates' => 'internal_id',
        'Plans' => 'internal_id',
    ];

    // tables that can't use big delete
    private const NON_ID_NON_KEY_TABLES = [
        'EmailTemplates' => true,
        'EmailTemplateOptions' => true,
        'Themes' => true,
    ];

    public function __construct(
        private Connection $database,
        private SearchReset $searchReset,
        private AccountsPayableLedger $apLedger,
    ) {
    }

    /**
     * When in test mode clears any account data
     * (but not settings).
     *
     * @throws InvalidArgumentException when not in test mode
     */
    public function clearData(Company $company): void
    {
        if (!$company->test_mode || !$company->hasId()) {
            throw new InvalidArgumentException('Clearing data is only permitted in test mode.');
        }

        /* Wipe data */

        $this->wipeTables(self::DATA_TABLES['all'], $company);
        if ($company->features->has('accounts_receivable')) {
            $this->wipeTables(self::DATA_TABLES['accounts_receivable'], $company);
        }
        if ($company->features->has('accounts_payable')) {
            $this->wipeTables(self::DATA_TABLES['accounts_payable'], $company);
            $this->deleteLedger($this->apLedger->getLedger($company));
            // create a new ledger
            $this->apLedger->getLedger($company);
        }
        if ($company->features->has('subscription_billing')) {
            $this->wipeTables(self::DATA_TABLES['subscription_billing'], $company);
        }

        /* Reset Auto-Numbering Sequences */

        $this->database->update('AutoNumberSequences', ['next' => 1], ['tenant_id' => $company->id()]);

        /* Reset search indexes */

        $this->searchReset->run($company);
    }

    /**
     * When in test mode clears specific settings.
     *
     * @throws InvalidArgumentException when not in test mode
     */
    public function clearSettings(Company $company): void
    {
        if (!$company->test_mode || !$company->hasId()) {
            throw new InvalidArgumentException('Clearing data is only permitted in test mode.');
        }

        /* Wipe settings data */

        $this->wipeTables(self::SETTINGS_TABLES['all'], $company);
        if ($company->features->has('accounts_receivable')) {
            $this->wipeTables(self::SETTINGS_TABLES['accounts_receivable'], $company);
        }
        if ($company->features->has('accounts_payable')) {
            $this->wipeTables(self::SETTINGS_TABLES['accounts_payable'], $company);
        }
        if ($company->features->has('subscription_billing')) {
            $this->wipeTables(self::SETTINGS_TABLES['subscription_billing'], $company);
        }
    }

    /**
     * @param string[] $tables
     */
    private function wipeTables(array $tables, Company $company): void
    {
        foreach ($tables as $table) {
            if (isset(self::NON_ID_NON_KEY_TABLES[$table])) {
                $this->database->delete($table, ['tenant_id' => $company->id()]);
                continue;
            }
            $id = self::NON_ID_TABLES[$table] ?? 'id';
            DatabaseHelper::efficientBigDelete($this->database, $table, 'tenant_id = '.$company->id(), 1000, $id);
        }
    }

    private function deleteLedger(Ledger $ledger): void
    {
        // Delete ledger data in this order due to foreign keys
        $this->database->executeStatement('DELETE LedgerEntries FROM LedgerEntries JOIN LedgerTransactions ON LedgerTransactions.id=transaction_id JOIN Documents ON Documents.id=LedgerTransactions.document_id WHERE Documents.ledger_id='.$ledger->id);
        $this->database->executeStatement('DELETE LedgerTransactions FROM LedgerTransactions JOIN Documents ON Documents.id=LedgerTransactions.document_id WHERE Documents.ledger_id='.$ledger->id.' AND original_transaction_id IS NOT NULL');
        $this->database->executeStatement('DELETE LedgerTransactions FROM LedgerTransactions JOIN Documents ON Documents.id=LedgerTransactions.document_id WHERE Documents.ledger_id='.$ledger->id);
        $this->database->delete('Documents', ['ledger_id' => $ledger->id]);
        $this->database->delete('Accounts', ['ledger_id' => $ledger->id]);
        $this->database->delete('Ledgers', ['id' => $ledger->id]);
    }
}
