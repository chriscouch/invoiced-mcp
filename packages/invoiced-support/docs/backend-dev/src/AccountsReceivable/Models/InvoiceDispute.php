<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\Enums\DisputeStatus;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int                $id
 * @property Invoice            $invoice
 * @property DisputeStatus      $status
 * @property DisputeReason|null $reason
 * @property string|null        $notes
 * @property string             $currency
 * @property float              $amount
 */
class InvoiceDispute extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'invoice' => new Property(
                belongs_to: Invoice::class,
            ),
            'status' => new Property(
                required: true,
                enum_class: DisputeStatus::class,
            ),
            'reason' => new Property(
                null: true,
                belongs_to: DisputeReason::class,
            ),
            'notes' => new Property(
                null: true,
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
        ];
    }
}
