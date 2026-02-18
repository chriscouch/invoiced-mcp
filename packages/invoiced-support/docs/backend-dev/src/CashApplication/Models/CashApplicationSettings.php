<?php

namespace App\CashApplication\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $tenant_id
 * @property string $short_pay_units
 * @property int    $short_pay_amount
 */
class CashApplicationSettings extends MultitenantModel
{
    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'short_pay_units' => new Property(
                type: Type::STRING,
                validate: ['enum', 'choices' => ['percent', 'dollars']],
                default: 'percent',
            ),
            'short_pay_amount' => new Property(
                type: Type::INTEGER,
                default: 10,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::deleting(function (): never {
            throw new ListenerException('Deleting settings not permitted');
        });

        parent::initialize();
    }
}
