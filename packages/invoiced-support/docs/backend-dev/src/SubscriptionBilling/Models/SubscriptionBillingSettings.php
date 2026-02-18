<?php

namespace App\SubscriptionBilling\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int             $tenant_id
 * @property string          $after_subscription_nonpayment
 * @property bool            $subscription_draft_invoices
 * @property MrrVersion|null $mrr_version
 */
class SubscriptionBillingSettings extends MultitenantModel
{
    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'after_subscription_nonpayment' => new Property(
                validate: ['enum', 'choices' => ['cancel', 'do_nothing']],
                default: 'do_nothing',
            ),
            'subscription_draft_invoices' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'mrr_version' => new Property(
                null: true,
                in_array: false,
                belongs_to: MrrVersion::class,
            ),
            'mrr_version_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
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
