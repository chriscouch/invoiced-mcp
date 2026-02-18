<?php

namespace App\Integrations\AccountingSync\Models;

use App\CashApplication\Models\Transaction;
use App\Core\Orm\Property;

/**
 * @property Transaction $transaction
 * @property int         $transaction_id
 */
class AccountingTransactionMapping extends AbstractMapping
{
    protected static function getIDProperties(): array
    {
        return ['transaction_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'transaction' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Transaction::class,
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
}
