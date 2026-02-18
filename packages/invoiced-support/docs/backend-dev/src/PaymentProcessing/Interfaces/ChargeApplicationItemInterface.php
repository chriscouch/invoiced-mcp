<?php

namespace App\PaymentProcessing\Interfaces;

use App\AccountsPayable\Models\PayableDocument;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentFlowApplication;

/**
 * Represents the application of a portion of a charge
 * to a document. In some cases the document might not
 * be present and instead the payment is applied to the
 * customer's credit balance or represents a convenience fee.
 */
interface ChargeApplicationItemInterface
{
    /**
     * Gets the amount being applied to this document.
     */
    public function getAmount(): Money;

    /**
     * Gets the document associated with this payment application item.
     */
    public function getDocument(): ?ReceivableDocument;

    /**
     * Builds a payment application line to be used as an
     * entry in the payment `applied_to` array.
     */
    public function build(): array;

    /**
     * Builds a payment application line to be used as an
     * entry in the payment `applied_to` array.
     */
    public function buildApplication(): PaymentFlowApplication;

    /**
     * Gets the associated payable document, if any.
     */
    public function getPayableDocument(): ?PayableDocument;
}
