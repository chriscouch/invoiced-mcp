<?php

namespace App\Integrations\AccountingSync\Models;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;

/**
 * @property Customer $customer
 * @property int      $customer_id
 */
class AccountingCustomerMapping extends AbstractMapping
{
    protected static function getIDProperties(): array
    {
        return ['customer_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Customer::class,
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

    public static function findForCustomer(Customer $customer, IntegrationType $integration): ?self
    {
        return self::where('integration_id', $integration->value)
            ->where('customer_id', $customer)
            ->oneOrNull();
    }
}
