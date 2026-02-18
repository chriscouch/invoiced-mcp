<?php

namespace App\Integrations\QuickBooksOnline\Models;

use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string|null $discount_account
 * @property string|null $tax_code
 * @property bool        $match_tax_rates
 * @property string|null $undeposited_funds_account
 * @property bool        $namespace_customers
 * @property bool        $namespace_invoices
 * @property bool        $namespace_items
 * @property string|null $custom_field_1
 * @property string|null $custom_field_2
 * @property string|null $custom_field_3
 */
class QuickBooksOnlineSyncProfile extends AccountingSyncProfile
{
    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        $properties = parent::getProperties();
        unset($properties['integration']);
        unset($properties['parameters']);

        $properties = array_merge($properties, [
            'discount_account' => new Property(
                null: true,
            ),
            'tax_code' => new Property(
                null: true,
            ),
            'match_tax_rates' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'undeposited_funds_account' => new Property(
                null: true,
            ),
            'namespace_customers' => new Property(
                type: Type::BOOLEAN,
            ),
            'namespace_invoices' => new Property(
                type: Type::BOOLEAN,
            ),
            'namespace_items' => new Property(
                type: Type::BOOLEAN,
            ),
            'custom_field_1' => new Property(
                null: true,
            ),
            'custom_field_2' => new Property(
                null: true,
            ),
            'custom_field_3' => new Property(
                null: true,
            ),
        ]);

        return $properties;
    }

    public function getIntegrationType(): IntegrationType
    {
        return IntegrationType::QuickBooksOnline;
    }
}
