<?php

namespace App\Tests\Core\Search;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Search\Driver\Database\DatabaseDriver;
use App\Core\Search\Driver\Database\DatabaseIndex;
use App\Core\Search\Libs\IndexRegistry;
use App\Tests\AppTestCase;

class DatabaseDriverTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testGetIndex(): void
    {
        $driver = $this->getDriver();

        $index = $driver->getIndex(new Company(['id' => 1]), Customer::class);
        $this->assertInstanceOf(DatabaseIndex::class, $index);
        $this->assertEquals(Customer::class, $index->getName());
    }

    public function testCreateIndex(): void
    {
        $driver = $this->getDriver();

        $index = $driver->createIndex(new Company(['id' => 1]), Customer::class, 'test');
        $this->assertInstanceOf(DatabaseIndex::class, $index);
        $this->assertEquals(Customer::class, $index->getName());
    }

    public function testSearch(): void
    {
        $driver = $this->getDriver();

        $results = $driver->search(self::$company, 'example.com', Customer::class, 5);
        $this->assertCount(1, $results);
        $this->assertEquals(self::$customer->id(), $results[0]['id']);
        $this->assertEquals('customer', $results[0]['object']);

        $results = $driver->search(self::$company, 'example.com', Customer::class, 1);
        $this->assertCount(1, $results);
        $this->assertEquals(self::$customer->id(), $results[0]['id']);
        $this->assertEquals('customer', $results[0]['object']);

        $results = $driver->search(self::$company, 'no results', Customer::class, 5);
        $this->assertCount(0, $results);
    }

    private function getDriver(): DatabaseDriver
    {
        return new DatabaseDriver(new IndexRegistry());
    }
}
