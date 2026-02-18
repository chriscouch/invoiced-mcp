<?php

namespace App\Integrations\Xero\Models;

use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int         $integration_version
 * @property string|null $item_account
 * @property string|null $discount_account
 * @property string|null $sales_tax_account
 * @property string|null $convenience_fee_account
 * @property string      $tax_mode
 * @property string|null $tax_type
 * @property bool        $add_tax_line_item
 * @property bool        $send_item_code
 */
class XeroSyncProfile extends AccountingSyncProfile
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
            'integration_version' => new Property(
                type: Type::INTEGER,
                default: 2,
            ),
            'item_account' => new Property(
                null: true,
            ),
            'discount_account' => new Property(
                null: true,
            ),
            'tax_mode' => new Property(
                type: 'string',
                validate: ['enum', 'choices' => ['tax_line_item', 'inherit', 'match_tax_rate']],
                default: 'tax_line_item',
            ),
            'sales_tax_account' => new Property(
                null: true,
            ),
            'convenience_fee_account' => new Property(
                null: true,
            ),
            'send_item_code' => new Property(
                type: Type::BOOLEAN,
            ),
            'tax_type' => new Property(
                null: true,
            ),
            'add_tax_line_item' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
        ]);

        return $properties;
    }

    public function getIntegrationType(): IntegrationType
    {
        return IntegrationType::Xero;
    }
}
