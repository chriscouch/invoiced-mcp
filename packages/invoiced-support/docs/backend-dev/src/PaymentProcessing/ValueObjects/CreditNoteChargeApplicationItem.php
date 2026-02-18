<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Interfaces\CreditChargeApplicationItemInterface;
use App\PaymentProcessing\Models\PaymentFlowApplication;

/**
 * @property ReceivableDocument $document
 */
final class CreditNoteChargeApplicationItem extends AbstractChargeApplicationItem implements CreditChargeApplicationItemInterface
{
    public function __construct(
        Money $amount,
        private CreditNote $creditNote,
        ReceivableDocument $document,
    ) {
        parent::__construct($amount, $document);
    }

    public function build(): array
    {
        $documentType = $this->document->object;

        return [
            'type' => PaymentItemType::CreditNote->value,
            'document_type' => $documentType,
            'credit_note' => $this->creditNote,
            'amount' => $this->amount->toDecimal(),
            $documentType => $this->document,
        ];
    }

    public function buildApplication(): PaymentFlowApplication
    {
        $documentType = ObjectType::fromModel($this->document);
        $application = new PaymentFlowApplication();
        $application->type = PaymentItemIntType::CreditNote;
        $application->document_type = $documentType;
        $application->credit_note = $this->creditNote;
        $application->amount = $this->amount->toDecimal();
        $application->{$documentType->typeName()} = $this->document;

        return $application;
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->amount->currency, 0);
    }

    public function getCredit(): Money
    {
        return $this->amount;
    }
}
