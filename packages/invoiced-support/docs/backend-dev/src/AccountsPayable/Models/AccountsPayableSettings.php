<?php

namespace App\AccountsPayable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Sending\Email\Models\Inbox;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int        $tenant_id
 * @property array      $aging_buckets
 * @property string     $aging_date
 * @property Inbox|null $inbox
 * @property int|null   $inbox_id
 */
class AccountsPayableSettings extends MultitenantModel
{
    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'aging_buckets' => new Property(
                type: Type::ARRAY,
                default: [0, 8, 15, 31, 61],
            ),
            'aging_date' => new Property(
                validate: ['enum', 'choices' => ['date', 'due_date']],
                default: 'date',
            ),
            'inbox' => new Property(
                null: true,
                belongs_to: Inbox::class,
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
