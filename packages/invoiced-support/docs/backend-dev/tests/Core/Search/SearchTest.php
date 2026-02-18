<?php

namespace App\Tests\Core\Search;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\Search\Driver\Database\DatabaseDriver;
use App\Tests\AppTestCase;

class SearchTest extends AppTestCase
{
    public function testGetDriver(): void
    {
        $search = self::getService('test.search');
        $company = new Company(['id' => 1]);
        $driver = $search->getDriver($company);
        $this->assertInstanceOf(DatabaseDriver::class, $driver);
    }

    public function testGetIndex(): void
    {
        $search = self::getService('test.search');

        $index = $search->getIndex(new Company(['id' => 1]), Customer::class);
        $this->assertEquals(Customer::class, $index->getName());

        $index2 = $search->getIndex(new Company(['id' => 1]), Customer::class);
        $this->assertEquals($index, $index2);

        $index3 = $search->getIndex(new Company(['id' => 1]), Invoice::class);
        $this->assertEquals(Invoice::class, $index3->getName());
    }
}
