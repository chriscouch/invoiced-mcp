<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;

final class PaymentItemsForm
{
    const ADVANCE_PAYMENT_KEY = '__advance__';
    const CREDIT_BALANCE_KEY = '__credit_balance__';

    /**
     * @param ReceivableDocument[] $documents
     * @param string[]             $selectedDocuments
     */
    public function __construct(
        public readonly Company $company,
        public readonly Customer $customer,
        public readonly array $documents,
        public readonly bool $advancePayment,
        public readonly array $selectedDocuments,
        public readonly Money $creditBalance,
    ) {
    }

    public function hasNonCreditItems(): bool
    {
        if ($this->advancePayment) {
            return true;
        }

        foreach ($this->documents as $document) {
            if (!$document instanceof CreditNote) {
                return true;
            }
        }

        return false;
    }

    public function choicesCount(): int
    {
        return count($this->documents) + ($this->advancePayment ? 1 : 0) + ($this->creditBalance->isPositive() ? 1 : 0);
    }

    public function isCreditBalanceSelected(): bool
    {
        return in_array(self::CREDIT_BALANCE_KEY, $this->selectedDocuments);
    }

    public function isAdvancePaymentSelected(): bool
    {
        return in_array(self::ADVANCE_PAYMENT_KEY, $this->selectedDocuments);
    }

    public function isDocumentSelected(ReceivableDocument $document): bool
    {
        return in_array($document->client_id, $this->selectedDocuments);
    }

    public function getAvailableCreditNoteClientIds(): array
    {
        $result = [];
        foreach ($this->documents as $document) {
            if ($document instanceof CreditNote) {
                $result[] = $document->client_id;
            }
        }

        return $result;
    }

    public function getAvailableEstimateClientIds(): array
    {
        $result = [];
        foreach ($this->documents as $document) {
            if ($document instanceof Estimate) {
                $result[] = $document->client_id;
            }
        }

        return $result;
    }

    public function getAvailableInvoiceClientIds(): array
    {
        $result = [];
        foreach ($this->documents as $document) {
            if ($document instanceof Invoice) {
                $result[] = $document->client_id;
            }
        }

        return $result;
    }
}
