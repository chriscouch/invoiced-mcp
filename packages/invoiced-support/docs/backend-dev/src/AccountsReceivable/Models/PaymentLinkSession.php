<?php

namespace App\AccountsReceivable\Models;

use App\CashApplication\Models\Payment;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use DateTimeInterface;

/**
 * @property int                    $id
 * @property PaymentLink            $payment_link
 * @property DateTimeInterface|null $completed_at
 * @property Customer|null          $customer
 * @property Invoice|null           $invoice
 * @property Payment|null           $payment
 * @property string|null            $hash
 */
class PaymentLinkSession extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'payment_link' => new Property(
                in_array: false,
                belongs_to: PaymentLink::class,
            ),
            'completed_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'invoice' => new Property(
                null: true,
                belongs_to: Invoice::class,
            ),
            'payment' => new Property(
                null: true,
                belongs_to: Payment::class,
            ),
            'hash' => new Property(
                null: true,
                in_array: false,
            ),
        ];
    }
}
