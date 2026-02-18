<?php

namespace App\Tests\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

abstract class AbstractWriterTestCase extends AppTestCase
{
    protected function createTransaction(Invoice $invoice, float $amount, ?Transaction $parentTransaction = null): Transaction
    {
        $transaction = new Transaction();
        $transaction->tenant_id = (int) self::$company->id();
        $transaction->setCustomer(self::$customer);
        $transaction->setInvoice($invoice);
        $transaction->amount = $amount;
        if ($parentTransaction) {
            $transaction->setParentTransaction($parentTransaction);
        }
        $transaction->saveOrFail();

        return $transaction;
    }

    protected static function hasNetSuiteInvoice(): Invoice
    {
        self::hasInvoice();
        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = self::$invoice;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = '3';
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        return self::$invoice;
    }

    protected static function hasNetSuiteCreditNote(string $accountingId = '4'): CreditNote
    {
        self::hasUnappliedCreditNote();
        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = self::$creditNote;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = $accountingId;
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        return self::$creditNote;
    }

    protected static function hasNetSuiteCustomer(string $accountingId = '1'): void
    {
        self::hasCustomer();
        $mapping = new AccountingCustomerMapping();
        $mapping->customer = self::$customer;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = $accountingId;
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();
    }
}
