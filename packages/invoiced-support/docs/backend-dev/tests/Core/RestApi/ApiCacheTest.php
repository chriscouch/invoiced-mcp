<?php

namespace App\Tests\Core\RestApi;

use App\AccountsReceivable\Models\Customer;
use App\Core\Authentication\Models\User;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\Utils\SimpleCache;
use App\Tests\AppTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ApiCacheTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getCache(): ApiCache
    {
        return new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter()));
    }

    public function testGetCachedQueryCount(): void
    {
        $cache = $this->getCache();
        $query = Customer::where('active', true)
            ->where('name', '', '<>')
            ->where('name', 'Invoiced')
            ->where('name', ['Test', 'Test 2'], '<>')
            ->where('name', new User(['id' => -1]), '<>')
            ->sort('name ASC,id ASC');

        $this->assertEquals(0, $cache->getCachedQueryCount($query, false));

        $customer = new Customer();
        $customer->name = 'Invoiced';
        $customer->country = 'US';
        $customer->saveOrFail();
        $this->assertEquals(0, $cache->getCachedQueryCount($query, false));
        $this->assertEquals(1, $cache->getCachedQueryCount($query, true));
        $this->assertEquals(1, $cache->getCachedQueryCount($query, false));
    }

    public function testGetPaginationCursor(): void
    {
        $cache = $this->getCache();
        $query = Customer::where('active', true)
            ->where('name', '', '<>')
            ->where('name', 'Invoiced')
            ->where('name', ['Test', 'Test 2'], '<>')
            ->where('name', new User(['id' => -1]), '<>')
            ->sort('name ASC,id ASC');

        $this->assertNull($cache->getPaginationCursor($query, 0));
        $this->assertNull($cache->getPaginationCursor($query, 100));

        $cache->storePaginationCursor($query, 100, 'test');
        $this->assertNull($cache->getPaginationCursor($query, 0));
        $this->assertNull($cache->getPaginationCursor($query, 99));
        $this->assertEquals('test', $cache->getPaginationCursor($query, 100));
        $this->assertNull($cache->getPaginationCursor($query, 101));

        $query = Customer::where('active', true)
            ->where('name', 'Invoiced')
            ->where('name', ['Test', 'Test 2'], '<>')
            ->where('name', new User(['id' => -1]), '<>')
            ->sort('name ASC,id ASC');
        $this->assertNull($cache->getPaginationCursor($query, 100));
    }
}
