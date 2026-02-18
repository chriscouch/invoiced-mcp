<?php

namespace App\Tests\Core\Multitenant;

use App\Companies\Models\Company;
use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Multitenant\TenantContext;
use App\Tests\AppTestCase;

class TenantContextTest extends AppTestCase
{
    private function getContext(): TenantContext
    {
        return new TenantContext(self::getService('test.event_spool'), self::getService('test.email_spool'));
    }

    public function testSet(): void
    {
        $tenant = $this->getContext();

        $company = new Company(['id' => 1]);
        $tenant->set($company);
        $this->assertEquals($company, $tenant->get());
    }

    public function testHas(): void
    {
        $tenant = $this->getContext();

        $this->assertFalse($tenant->has());
        $company = new Company(['id' => 1]);
        $tenant->set($company);
        $this->assertTrue($tenant->has());
    }

    public function testGetNoTenant(): void
    {
        $this->expectException(MultitenantException::class);

        $tenant = $this->getContext();
        $tenant->get();
    }

    public function testClear(): void
    {
        $tenant = $this->getContext();

        $company = new Company(['id' => 1]);
        $tenant->set($company);
        $this->assertTrue($tenant->has());
        $tenant->clear();
        $this->assertFalse($tenant->has());
    }

    public function testRunAsNoPreviousTenant(): void
    {
        $tenant = $this->getContext();
        $this->assertFalse($tenant->has());

        $company = new Company(['id' => 1]);
        $tenant->runAs($company, function () use ($tenant, $company) {
            $this->assertEquals($company, $tenant->get());
        });

        $this->assertFalse($tenant->has());
    }

    public function testRunAsWithPreviousTenant(): void
    {
        $tenant = $this->getContext();

        $company1 = new Company(['id' => 1]);
        $tenant->set($company1);

        $company2 = new Company(['id' => 2]);
        $tenant->runAs($company2, function () use ($tenant, $company2) {
            $this->assertEquals($company2, $tenant->get());
        });

        $this->assertEquals($company1, $tenant->get());
    }
}
