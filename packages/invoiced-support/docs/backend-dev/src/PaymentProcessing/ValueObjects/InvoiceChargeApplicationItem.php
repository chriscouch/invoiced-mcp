<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsPayable\Models\PayableDocument;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentFlowApplication;

/**
 * @property Invoice $document
 */
final class InvoiceChargeApplicationItem extends AbstractChargeApplicationItem
{
    public function __construct(Money $amount, Invoice $invoice, ?PayableDocument $payableDocument = null)
    {
        parent::__construct($amount, $invoice, $payableDocument);
    }

    public function build(): array
    {
        return [
            'type' => PaymentItemType::Invoice->value,
            'invoice' => $this->document,
            'amount' => $this->amount->toDecimal(),
        ];
    }

    public function buildApplication(): PaymentFlowApplication
    {
        $application = new PaymentFlowApplication();
        $application->type = PaymentItemIntType::Invoice;
        $application->invoice = $this->document;
        $application->amount = $this->amount->toDecimal();

        return $application;
    }
}
