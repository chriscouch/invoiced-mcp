<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class Invoice extends Model
{
    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                required: true,
                belongs_to: Customer::class,
            ),
        ];
    }
}
