<?php

namespace App\Integrations\AccountingSync;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\Orm\Model;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingItemMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;

class AccountingMappingFactory
{
    public static function getInstance(Model $model): ?AbstractMapping
    {
        if ($model instanceof CreditNote) {
            $mapping = new AccountingCreditNoteMapping();
            $mapping->credit_note = $model;

            return $mapping;
        }

        if ($model instanceof Customer) {
            $mapping = new AccountingCustomerMapping();
            $mapping->customer = $model;

            return $mapping;
        }

        if ($model instanceof Invoice) {
            $mapping = new AccountingInvoiceMapping();
            $mapping->invoice = $model;

            return $mapping;
        }

        if ($model instanceof Payment) {
            $mapping = new AccountingPaymentMapping();
            $mapping->payment = $model;

            return $mapping;
        }

        if ($model instanceof Transaction) {
            $mapping = new AccountingTransactionMapping();
            $mapping->transaction = $model;

            return $mapping;
        }

        if ($model instanceof Item) {
            $mapping = new AccountingItemMapping();
            $mapping->item = $model;

            return $mapping;
        }

        return null;
    }
}
