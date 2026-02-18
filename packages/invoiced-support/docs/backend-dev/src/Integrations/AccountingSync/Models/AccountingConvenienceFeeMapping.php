<?php

namespace App\Integrations\AccountingSync\Models;

use App\CashApplication\Models\Payment;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;

/**
 * @property Payment $payment
 * @property int     $payment_id
 */
class AccountingConvenienceFeeMapping extends AbstractMapping
{
    protected static function getProperties(): array
    {
        return [
            'payment' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Payment::class,
            ),
            'accounting_id' => new Property(
                required: true,
            ),
            'source' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['accounting_system', 'invoiced']],
            ),
        ];
    }

    protected static function getIDProperties(): array
    {
        return ['payment_id'];
    }

    public static function findForPayment(Payment $payment, IntegrationType $integration): ?self
    {
        return self::where('integration_id', $integration->value)
            ->where('payment_id', $payment)
            ->oneOrNull();
    }
}
