<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemIntType;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;

/**
 * @property int                $id
 * @property PaymentFlow        $payment_flow
 * @property PaymentItemIntType $type
 * @property float              $amount
 * @property Invoice|null       $invoice
 * @property CreditNote|null    $credit_note
 * @property Estimate|null      $estimate
 * @property ObjectType|null    $document_type
 */
class PaymentFlowApplication extends Model
{
    protected static function getProperties(): array
    {
        return [
            'payment_flow' => new Property(
                required: true,
                belongs_to: PaymentFlow::class,
            ),
            'type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: PaymentItemIntType::class,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'invoice' => new Property(
                null: true,
                belongs_to: Invoice::class,
            ),
            'estimate' => new Property(
                null: true,
                belongs_to: Estimate::class,
            ),
            'credit_note' => new Property(
                null: true,
                belongs_to: CreditNote::class
            ),
            'document_type' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: ObjectType::class,
            ),
        ];
    }

    /**
     * Converts the application to a split for the purpose of creating a Payment line item.
     */
    public function toPaymentSplit(): array
    {
        $result = [
            'type' => $this->type->toString(),
            'amount' => $this->amount,
        ];

        if ($invoice = $this->invoice) {
            $result['invoice'] = $invoice;
        }

        if ($creditNote = $this->credit_note) {
            $result['credit_note'] = $creditNote;
        }

        if ($estimate = $this->estimate) {
            $result['estimate'] = $estimate;
        }

        if ($documentType = $this->document_type) {
            $result['document_type'] = $documentType->toString();
        }

        return $result;
    }
}
