<?php

namespace App\CustomerPortal\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int         $tenant_id
 * @property bool        $enabled
 * @property bool        $allow_invoice_payment_selector
 * @property bool        $allow_partial_payments
 * @property bool        $allow_advance_payments
 * @property bool        $allow_autopay_enrollment
 * @property bool        $allow_billing_portal_cancellations
 * @property bool        $billing_portal_show_company_name
 * @property bool        $allow_billing_portal_profile_changes
 * @property string      $google_analytics_id
 * @property bool        $require_authentication
 * @property string|null $customer_portal_auth_url
 * @property bool        $include_sub_customers
 * @property bool        $show_powered_by
 * @property bool        $allow_editing_contacts
 * @property bool        $allow_invoice_disputes
 * @property bool        $invoice_payment_to_item_selection
 * @property string      $welcome_message
 * @property bool        $payment_links
 */
class CustomerPortalSettings extends MultitenantModel
{
    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'allow_invoice_payment_selector' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'allow_partial_payments' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'allow_advance_payments' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'allow_autopay_enrollment' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'allow_billing_portal_cancellations' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'billing_portal_show_company_name' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'allow_billing_portal_profile_changes' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'google_analytics_id' => new Property(
                type: Type::STRING,
            ),
            'customer_portal_auth_url' => new Property(
                type: Type::STRING,
                null: true,
                validate: 'url',
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'include_sub_customers' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'show_powered_by' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'require_authentication' => new Property(
                type: Type::BOOLEAN,
            ),
            'allow_editing_contacts' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'invoice_payment_to_item_selection' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'allow_invoice_disputes' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'welcome_message' => new Property(
                type: Type::STRING,
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
