<?php

namespace App\Tests\Core\Multitenant;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Product;
use App\Core\Multitenant\Exception\MultitenantException;
use App\Tests\AppTestCase;

class MultiTenantTraitTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        // create a new company
        self::getService('test.tenant')->clear();
        self::$company2 = new Company();
        self::$company2->name = 'Test';
        self::$company2->username = 'test2'.time();
        self::$company2->saveOrFail();
        self::getService('test.product_installer')->install(Product::where('name', 'Accounts Receivable Free')->one(), self::$company2);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::getService('test.tenant')->set(self::$company);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        self::$company2->delete();
    }

    public function testSetTenantId(): void
    {
        $customer = new Customer();
        $customer->tenant_id = 10;
        $this->assertEquals(10, $customer->tenant_id);
    }

    public function testGetTenantId(): void
    {
        $customer = new Customer();
        $customer->tenant_id = 11;
        $this->assertEquals(11, $customer->tenant_id);
    }

    public function testCreateWithoutTenant(): void
    {
        $this->expectException(MultitenantException::class);
        $this->expectExceptionMessage('Tried to save Customer for a tenant (# '.self::$company2->id().') different than the current tenant (# '.self::$company->id().')!');

        $customer = new Customer();
        $customer->tenant_id = (int) self::$company2->id();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->save();
    }

    public function testCreateDifferentTenantThanCurrent(): void
    {
        $this->expectException(MultitenantException::class);
        $this->expectExceptionMessage('Attempted to save Customer without specifying a tenant or setting the current tenant on the DI container!');

        self::getService('test.tenant')->clear();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->save();
    }

    public function testEditCannotChangeTenant(): void
    {
        self::hasCustomer();
        self::$customer->tenant_id = (int) self::$company2->id();
        $this->assertTrue(self::$customer->save());
        $this->assertEquals(self::$company->id(), self::$customer->tenant_id);
    }

    public function testQueryCurrentTenantMissing(): void
    {
        $this->expectException(MultitenantException::class);
        $this->expectExceptionMessage('Attempted to query Customer without setting the current tenant on the DI container!');

        self::getService('test.tenant')->clear();

        Customer::query();
    }

    public function testQuery(): void
    {
        self::getService('test.tenant')->set(self::$company);
        $query = Customer::query();

        $this->assertEquals(self::$company->id(), $query->getWhere()['tenant_id']);
    }

    public function testQueryWithTenant(): void
    {
        $company = new Company(['id' => 10]);
        $query = Customer::queryWithTenant($company);

        $this->assertEquals(10, $query->getWhere()['tenant_id']);
    }

    public function testQueryWithoutMultitenancyUnsafe(): void
    {
        $query = Customer::queryWithoutMultitenancyUnsafe();
        $this->assertFalse(isset($query->getWhere()['tenant_id']));
    }

    public function testRelation(): void
    {
        // create an object on that tenant
        self::getService('test.tenant')->set(self::$company2);
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $this->assertTrue($customer->save());
        $this->assertEquals(self::$company2->id(), $customer->tenant_id);

        // reference the object from our other tenant
        self::getService('test.tenant')->set(self::$company);
        $invoice = new Invoice();
        $invoice->customer = (int) $customer->id();

        // the object should not exist
        $relation = $invoice->relation('customer');
        $this->assertNull($relation);
    }
}
