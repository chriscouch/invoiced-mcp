<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class Customer extends Model
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(),
            'payment_method' => new Property(
                morphs_to: [
                    'card' => Card::class,
                    'bank_account' => BankAccount::class,
                ],
            ),
        ];
    }
}
