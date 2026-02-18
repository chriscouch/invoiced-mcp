<?php

namespace App\CashApplication\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\RemittanceAdviceException;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property RemittanceAdvice               $remittance_advice
 * @property string                         $document_number
 * @property ObjectType|null                $document_type
 * @property Invoice|null                   $invoice
 * @property CreditNote|null                $credit_note
 * @property float                          $gross_amount_paid
 * @property float                          $discount
 * @property float                          $net_amount_paid
 * @property string|null                    $description
 * @property RemittanceAdviceException|null $exception
 */
class RemittanceAdviceLine extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'remittance_advice' => new Property(
                belongs_to: RemittanceAdvice::class,
            ),
            'document_number' => new Property(),
            'document_type' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: ObjectType::class,
            ),
            'invoice' => new Property(
                null: true,
                belongs_to: Invoice::class,
            ),
            'credit_note' => new Property(
                null: true,
                belongs_to: CreditNote::class,
            ),
            'payment_date' => new Property(
                type: Type::DATE,
            ),
            'payment_method' => new Property(),
            'gross_amount_paid' => new Property(
                type: Type::FLOAT,
            ),
            'discount' => new Property(
                type: Type::FLOAT,
            ),
            'net_amount_paid' => new Property(
                type: Type::FLOAT,
            ),
            'description' => new Property(
                null: true,
            ),
            'exception' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: RemittanceAdviceException::class,
            ),
        ];
    }
}
