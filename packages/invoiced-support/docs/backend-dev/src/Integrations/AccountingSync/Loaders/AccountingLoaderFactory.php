<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingItem;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use InvalidArgumentException;

class AccountingLoaderFactory
{
    public function __construct(
        private AccountingCreditNoteLoader $creditNoteLoader,
        private AccountingCustomerLoader $customerLoader,
        private AccountingInvoiceLoader $invoiceLoader,
        private AccountingItemLoader $itemLoader,
        private AccountingPaymentLoader $paymentLoader,
    ) {
    }

    public function get(AbstractAccountingRecord $record): LoaderInterface
    {
        return match (get_class($record)) {
            AccountingCreditNote::class => $this->creditNoteLoader,
            AccountingCustomer::class => $this->customerLoader,
            AccountingInvoice::class => $this->invoiceLoader,
            AccountingItem::class => $this->itemLoader,
            AccountingPayment::class => $this->paymentLoader,
            default => throw new InvalidArgumentException('Invalid record type'),
        };
    }
}
