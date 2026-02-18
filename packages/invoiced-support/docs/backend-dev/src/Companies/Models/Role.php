<?php

namespace App\Companies\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $id
 * @property string $name
 * @property bool   $business_admin
 * @property bool   $business_billing
 * @property bool   $catalog_edit
 * @property bool   $charges_create
 * @property bool   $comments_create
 * @property bool   $credit_notes_create
 * @property bool   $credit_notes_delete
 * @property bool   $credit_notes_edit
 * @property bool   $credit_notes_issue
 * @property bool   $credit_notes_void
 * @property bool   $credits_apply
 * @property bool   $credits_create
 * @property bool   $customers_create
 * @property bool   $customers_delete
 * @property bool   $customers_edit
 * @property bool   $emails_send
 * @property bool   $estimates_create
 * @property bool   $estimates_delete
 * @property bool   $estimates_edit
 * @property bool   $estimates_issue
 * @property bool   $estimates_void
 * @property bool   $imports_create
 * @property bool   $invoices_create
 * @property bool   $invoices_delete
 * @property bool   $invoices_edit
 * @property bool   $invoices_issue
 * @property bool   $invoices_void
 * @property bool   $letters_send
 * @property bool   $notes_create
 * @property bool   $notes_delete
 * @property bool   $notes_edit
 * @property bool   $notifications_edit
 * @property bool   $payments_create
 * @property bool   $payments_delete
 * @property bool   $payments_edit
 * @property bool   $refunds_create
 * @property bool   $reports_create
 * @property bool   $settings_edit
 * @property bool   $subscriptions_create
 * @property bool   $subscriptions_delete
 * @property bool   $subscriptions_edit
 * @property bool   $tasks_create
 * @property bool   $tasks_delete
 * @property bool   $tasks_edit
 * @property bool   $text_messages_send
 * @property int    $internal_id
 * @property bool   $bills_create
 * @property bool   $bills_edit
 * @property bool   $bills_delete
 * @property bool   $vendor_payments_create
 * @property bool   $vendor_payments_edit
 * @property bool   $vendor_payments_delete
 * @property bool   $vendors_create
 * @property bool   $vendors_edit
 * @property bool   $vendors_delete
 */
class Role extends MultitenantModel
{
    use AutoTimestamps;

    const ADMINISTRATOR = 'administrator';

    public const PERMISSIONS = [
        'business_admin' => 'business.admin',
        'business_billing' => 'business.billing',
        'catalog_edit' => 'catalog.edit',
        'charges_create' => 'charges.create',
        'credits_create' => 'credits.create',
        'credits_apply' => 'credits.apply',
        'credit_notes_create' => 'credit_notes.create',
        'credit_notes_issue' => 'credit_notes.issue',
        'credit_notes_edit' => 'credit_notes.edit',
        'credit_notes_void' => 'credit_notes.void',
        'credit_notes_delete' => 'credit_notes.delete',
        'customers_create' => 'customers.create',
        'customers_edit' => 'customers.edit',
        'customers_delete' => 'customers.delete',
        'estimates_create' => 'estimates.create',
        'estimates_issue' => 'estimates.issue',
        'estimates_edit' => 'estimates.edit',
        'estimates_void' => 'estimates.void',
        'estimates_delete' => 'estimates.delete',
        'emails_send' => 'emails.send',
        'imports_create' => 'imports.create',
        'invoices_create' => 'invoices.create',
        'invoices_issue' => 'invoices.issue',
        'invoices_edit' => 'invoices.edit',
        'invoices_void' => 'invoices.void',
        'invoices_delete' => 'invoices.delete',
        'letters_send' => 'letters.send',
        'payments_create' => 'payments.create',
        'payments_edit' => 'payments.edit',
        'payments_delete' => 'payments.delete',
        'refunds_create' => 'refunds.create',
        'reports_create' => 'reports.create',
        'settings_edit' => 'settings.edit',
        'text_messages_send' => 'text_messages.send',
        'comments_create' => 'comments.create',
        'notes_create' => 'notes.create',
        'notes_edit' => 'notes.edit',
        'notes_delete' => 'notes.delete',
        'notifications_edit' => 'notifications.edit',
        'subscriptions_create' => 'subscriptions.create',
        'subscriptions_edit' => 'subscriptions.edit',
        'subscriptions_delete' => 'subscriptions.delete',
        'tasks_create' => 'tasks.create',
        'tasks_edit' => 'tasks.edit',
        'tasks_delete' => 'tasks.delete',
        'bills_create' => 'bills.create',
        'bills_edit' => 'bills.edit',
        'bills_delete' => 'bills.delete',
        'vendor_payments_create' => 'vendor_payments.create',
        'vendor_payments_edit' => 'vendor_payments.edit',
        'vendor_payments_delete' => 'vendor_payments.delete',
        'vendors_create' => 'vendors.create',
        'vendors_edit' => 'vendors.edit',
        'vendors_delete' => 'vendors.delete',
    ];

    protected static function getIDProperties(): array
    {
        return ['internal_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'name' => new Property(
                required: true,
            ),

            /* Permissions */

            'customers_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'customers_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'customers_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'internal_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'invoices_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'invoices_issue' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'invoices_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'invoices_void' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'invoices_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credit_notes_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credit_notes_issue' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credit_notes_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credit_notes_void' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credit_notes_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'estimates_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'estimates_issue' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'estimates_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'estimates_void' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'estimates_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'emails_send' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'text_messages_send' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'letters_send' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'payments_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'payments_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'payments_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'charges_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'refunds_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credits_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'credits_apply' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'imports_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'reports_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'settings_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'catalog_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'business_admin' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'business_billing' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'comments_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'notes_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'notes_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'notes_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'subscriptions_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'subscriptions_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'subscriptions_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'tasks_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'tasks_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'tasks_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'notifications_edit' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'bills_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'bills_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'bills_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'vendor_payments_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'vendor_payments_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'vendor_payments_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'vendors_create' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'vendors_edit' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'vendors_delete' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'setId']);

        parent::initialize();
    }

    //
    // Hooks
    //

    /**
     * Sets the id, if one is not given.
     */
    public static function setId(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->id) {
            $model->id = uniqid();
        }
    }

    /**
     * Gets the list of permissions allowed by this role.
     */
    public function permissions(): array
    {
        $result = ['accounts.read'];
        foreach (self::PERMISSIONS as $k => $permission) {
            if ($this->$k) {
                $result[] = $permission;
            }
        }

        return $result;
    }

    public static function findById(string $model_id): ?self
    {
        return self::where('id', $model_id)->oneOrNull();
    }

    /**
     * @return array<string, int> hashmap of role id => internal_id
     */
    public static function getRoleHashMap(): array
    {
        $roles = Role::all();
        $roleIds = [];
        foreach ($roles as $role) {
            $roleIds[$role->id] = $role->internal_id;
        }

        return $roleIds;
    }
}
