<?php

namespace App\Tests\Companies\Models;

use App\Companies\Models\Role;
use App\Tests\AppTestCase;

class RoleTest extends AppTestCase
{
    private static Role $role;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testPermissions(): void
    {
        $role = new Role();

        $expected = [
            'accounts.read',
            'notifications.edit',
        ];
        $this->assertEquals($expected, $role->permissions());

        $role->invoices_create = true;
        $expected = [
            'accounts.read',
            'invoices.create',
            'notifications.edit',
        ];
        $this->assertEquals($expected, $role->permissions());

        $role->settings_edit = true;
        $role->catalog_edit = true;
        $role->business_admin = true;
        $role->business_billing = true;
        $expected = [
            'accounts.read',
            'business.admin',
            'business.billing',
            'catalog.edit',
            'invoices.create',
            'settings.edit',
            'notifications.edit',
        ];
        $this->assertEquals($expected, $role->permissions());
    }

    public function testCreate(): void
    {
        self::$role = new Role();
        self::$role->id = 'test_role';
        self::$role->name = 'Test';
        self::$role->invoices_create = true;
        $this->assertTrue(self::$role->save());
        $this->assertEquals(self::$company->id(), self::$role->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => 'test_role',
            'name' => 'Test',
            'business_admin' => false,
            'business_billing' => false,
            'catalog_edit' => false,
            'charges_create' => false,
            'credit_notes_create' => false,
            'credit_notes_delete' => false,
            'credit_notes_edit' => false,
            'credit_notes_issue' => false,
            'credit_notes_void' => false,
            'credits_apply' => false,
            'credits_create' => false,
            'customers_create' => false,
            'customers_delete' => false,
            'customers_edit' => false,
            'emails_send' => false,
            'estimates_create' => false,
            'estimates_delete' => false,
            'estimates_edit' => false,
            'estimates_issue' => false,
            'estimates_void' => false,
            'imports_create' => false,
            'invoices_create' => true,
            'invoices_delete' => false,
            'invoices_edit' => false,
            'invoices_issue' => false,
            'invoices_void' => false,
            'letters_send' => false,
            'payments_create' => false,
            'payments_delete' => false,
            'payments_edit' => false,
            'refunds_create' => false,
            'reports_create' => false,
            'settings_edit' => false,
            'text_messages_send' => false,
            'comments_create' => false,
            'notes_create' => false,
            'notes_edit' => false,
            'notes_delete' => false,
            'subscriptions_create' => false,
            'subscriptions_edit' => false,
            'subscriptions_delete' => false,
            'tasks_create' => false,
            'tasks_edit' => false,
            'tasks_delete' => false,
            'created_at' => self::$role->created_at,
            'updated_at' => self::$role->updated_at,
            'notifications_edit' => true,
            'bills_create' => false,
            'bills_edit' => false,
            'bills_delete' => false,
            'vendor_payments_create' => false,
            'vendor_payments_edit' => false,
            'vendor_payments_delete' => false,
            'vendors_create' => false,
            'vendors_edit' => false,
            'vendors_delete' => false,
        ];

        $this->assertEquals($expected, self::$role->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$role->catalog_edit = true;
        $this->assertTrue(self::$role->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$role->delete());
    }
}
