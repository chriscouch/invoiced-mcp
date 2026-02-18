<?php

namespace App\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingWriterInterface;
use App\Integrations\AccountingSync\Writers\NullWriter;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Intacct\Models\IntacctSyncProfile;

class IntacctWriterFactory
{
    public function __construct(
        private IntacctCustomerWriter $customers,
        private IntacctArInvoiceWriter $arInvoices,
        private IntacctCreditNoteWriter $creditNotes,
        private IntacctOrderEntryInvoiceWriter $oeInvoices,
        private IntacctPaymentWriter $payments,
    ) {
    }

    /**
     * @throws IntegrationException
     */
    public function get(AccountingWritableModelInterface $model): AccountingWriterInterface
    {
        if ($model instanceof Invoice) {
            /** @var IntacctSyncProfile|null $syncProfile */
            $syncProfile = AccountingSyncModelFactory::getSyncProfile(IntegrationType::Intacct, $model->tenant());
            if ($syncProfile?->write_to_order_entry) {
                return $this->oeInvoices;
            }
        }

        return match (get_class($model)) {
            Customer::class => $this->customers,
            Invoice::class => $this->arInvoices,
            Payment::class => $this->payments,
            CreditNote::class => $this->creditNotes,
            default => new NullWriter(),
        };
    }
}
