<?php

namespace App\PaymentProcessing\Enums;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\Models\InitiatedChargeDocument;
use App\PaymentProcessing\ValueObjects\AppliedCreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\ConvenienceFeeChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use InvalidArgumentException;

enum ChargeApplicationType: int
{
    case ConvenienceFeeChargeApplicationItem = 1;
    case CreditChargeApplicationItem = 2;
    case CreditNoteChargeApplicationItem = 3;
    case EstimateChargeApplicationItem = 4;
    case InvoiceChargeApplicationItem = 5;
    case AppliedCreditChargeApplicationItem = 6;

    public static function make(ChargeApplicationItemInterface $item): self
    {
        return match (get_class($item)) {
            AppliedCreditChargeApplicationItem::class => self::AppliedCreditChargeApplicationItem,
            ConvenienceFeeChargeApplicationItem::class => self::ConvenienceFeeChargeApplicationItem,
            CreditChargeApplicationItem::class => self::CreditChargeApplicationItem,
            CreditNoteChargeApplicationItem::class => self::CreditNoteChargeApplicationItem,
            EstimateChargeApplicationItem::class => self::EstimateChargeApplicationItem,
            InvoiceChargeApplicationItem::class => self::InvoiceChargeApplicationItem,
            default => throw new InvalidArgumentException('Unsupported charge application item'),
        };
    }

    public function chargeApplicationItem(InitiatedChargeDocument $document, Money $money): ChargeApplicationItemInterface
    {
        switch ($this) {
            case ChargeApplicationType::InvoiceChargeApplicationItem:
                $doc = Invoice::findOrFail($document->document_id);

                return new InvoiceChargeApplicationItem($money, $doc);
            case ChargeApplicationType::EstimateChargeApplicationItem:
                $doc = Estimate::findOrFail($document->document_id);

                return new EstimateChargeApplicationItem($money, $doc);
            case ChargeApplicationType::CreditNoteChargeApplicationItem:
                // TODO: this does not cover the document that the credit note is applied to
//                $doc = CreditNote::findOrFail($document->document_id);
//                return new CreditNoteChargeApplicationItem($doc, $money);
                throw new InvalidArgumentException('Credit note is missing document type');
            case ChargeApplicationType::CreditChargeApplicationItem:
                return new CreditChargeApplicationItem($money);
            case ChargeApplicationType::ConvenienceFeeChargeApplicationItem:
                return new ConvenienceFeeChargeApplicationItem($money);
            case ChargeApplicationType::AppliedCreditChargeApplicationItem:
                // TODO: this does not cover the document that the credit is applied to
//                return new AppliedCreditChargeApplicationItem($money, $doc);
                throw new InvalidArgumentException('Applied Credit is missing document type');
        }
    }
}
