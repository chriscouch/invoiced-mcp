<?php

namespace App\Integrations\Intacct\Models;

use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use stdClass;

/**
 * @property int           $integration_version
 * @property bool          $read_ar_adjustments
 * @property string|null   $item_account
 * @property string|null   $convenience_fee_account
 * @property string|null   $undeposited_funds_account
 * @property string|null   $bad_debt_account
 * @property bool          $write_to_order_entry
 * @property string|null   $item_location_id
 * @property string|null   $item_department_id
 * @property bool          $map_catalog_item_to_item_id
 * @property string        $customer_import_type
 * @property bool          $customer_top_level
 * @property array         $credit_note_types
 * @property array         $invoice_types
 * @property stdClass|null $invoice_import_mapping
 * @property stdClass|null $line_item_import_mapping
 * @property bool          $ship_to_invoice_distribution_list
 * @property string|null   $customer_read_query_addon
 * @property string|null   $ar_adjustment_read_query_addon
 * @property stdClass|null $invoice_import_query_addon
 * @property string|null   $invoice_location_id_filter
 * @property stdClass|null $customer_custom_field_mapping
 * @property stdClass|null $invoice_custom_field_mapping
 * @property stdClass|null $line_item_custom_field_mapping
 * @property stdClass|null $payment_plan_import_settings
 * @property string|null   $overpayment_location_id
 * @property string|null   $overpayment_department_id
 * @property int|null      $read_batch_size
 */
class IntacctSyncProfile extends AccountingSyncProfile
{
    const CUSTOMER_IMPORT_TYPE_CUSTOMER = 'customer';
    const CUSTOMER_IMPORT_TYPE_BILL_TO = 'bill_to_contact';

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
                default: 3,
            ),
            'read_ar_adjustments' => new Property(
                type: Type::BOOLEAN,
            ),
            'item_account' => new Property(
                null: true,
            ),
            'convenience_fee_account' => new Property(
                null: true,
            ),
            'undeposited_funds_account' => new Property(
                null: true,
            ),
            'bad_debt_account' => new Property(
                null: true,
            ),
            'write_to_order_entry' => new Property(
                type: Type::BOOLEAN,
            ),
            'item_location_id' => new Property(
                null: true,
            ),
            'item_department_id' => new Property(
                null: true,
            ),
            'map_catalog_item_to_item_id' => new Property(
                type: Type::BOOLEAN,
            ),
            'customer_import_type' => new Property(
                validate: ['enum', 'choices' => ['customer', 'bill_to_contact']],
                default: self::CUSTOMER_IMPORT_TYPE_CUSTOMER,
            ),
            'customer_top_level' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'credit_note_types' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'invoice_types' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'invoice_import_mapping' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'line_item_import_mapping' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'ship_to_invoice_distribution_list' => new Property(
                type: Type::BOOLEAN,
            ),
            'customer_read_query_addon' => new Property(
                null: true,
            ),
            'ar_adjustment_read_query_addon' => new Property(
                null: true,
            ),
            'invoice_import_query_addon' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'invoice_location_id_filter' => new Property(
                null: true,
            ),
            'customer_custom_field_mapping' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'invoice_custom_field_mapping' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'line_item_custom_field_mapping' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'payment_plan_import_settings' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
            'overpayment_location_id' => new Property(
                null: true,
            ),
            'overpayment_department_id' => new Property(
                null: true,
            ),
            'read_batch_size' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
        ]);

        return $properties;
    }

    public function getIntegrationType(): IntegrationType
    {
        return IntegrationType::Intacct;
    }
}
