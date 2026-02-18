<?php

namespace App\Tests\ActivityLog\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Query;
use App\Core\Utils\SimpleCache;
use App\ActivityLog\Api\ListEventsRoute;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\ActivityLog\Models\EventAssociation;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;
use Mockery;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Symfony\Component\HttpFoundation\Request;

class ListEventsRouteTest extends AppTestCase
{
    private static EventStorageInterface $storage;
    private static Mockery\LegacyMockInterface|Mockery\MockInterface|SimpleCache $cache;
    private static Mockery\LegacyMockInterface|Mockery\MockInterface|PsrCacheInterface $apiCache;
    private static array $events;
    private static ListEventsRoute $route;
    private static Connection $connection;
    private static int $perPage = 2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$storage = Mockery::mock(EventStorageInterface::class);
        self::$cache = Mockery::mock(SimpleCache::class);
        self::$apiCache = Mockery::mock(ApiCache::class);
        self::$connection = self::getService('test.database');
        self::$route = new ListEventsRoute(self::$storage, self::$connection, self::$cache, self::$apiCache);
        self::$route->setModelClass(Event::class);

        for ($i = 0; $i < self::$perPage * 2 + 1; ++$i) {
            self::$connection->insert('Events', [
                'tenant_id' => self::$company->id,
                'object_type' => 'customer',
                'object_id' => self::$customer->id,
            ]);
        }

        $events = Event::query()->all();

        foreach ($events as $event) {
            self::$events[] = $event;
            $ea = new EventAssociation();
            $ea->object = 'customer';
            $ea->object_type = 1;
            $ea->object_id = (string) self::$customer->id;
            $ea->event = $event->id;
            $ea->saveOrFail();
        }
    }

    private function applyQuery(int $page = 1): Query
    {
        $request = new Request([
            'page' => $page,
            'paginate' => 'none',
            'per_page' => self::$perPage,
        ]);
        self::$route->parseListParameters($request);
        $query = self::$route->buildQuery(new ApiCallContext(
            $request,
            ['paginate' => 'none'],
            [],
            self::$route->getDefinition()));
        self::$route->applyPaginationToQuery($query);

        return $query;
    }

    public function testBuildQueryNoData(): void
    {
        $query = $this->applyQuery();
        $this->assertEquals([
            'tenant_id' => self::$company->id,
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            array_map(fn ($item) => $item['id'], array_slice(self::$events, 0, self::$perPage)),
            array_map(fn ($item) => $item['id'], $query->execute())
        );
    }

    public function testBuildQueryRelatedToPage1NoCache(): void
    {
        self::$route->setRelatedTo('customer', (string) self::$customer->id);
        self::$cache->shouldReceive('get')->andReturn(false)->once();
        self::$cache->shouldReceive('set')->withArgs(function ($hash, $cursor) {
            return $cursor == self::$events[1]->id;
        })->once();
        $query = $this->applyQuery();
        $ids = array_map(fn ($item) => $item['id'], array_slice(self::$events, 0, self::$perPage));
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => 'id IN ('.implode(',', $ids).')',
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            $ids,
            array_map(fn ($item) => $item['id'], $query->execute())
        );
        $this->assertEquals(1, self::$route->getPage());
    }

    public function testBuildQueryRelatedToPage2NoCache(): void
    {
        self::$route->setRelatedTo('customer', (string) self::$customer->id);
        self::$cache->shouldReceive('get')->andReturn(false)->once();
        self::$cache->shouldReceive('set')->withArgs(function ($hash, $cursor) {
            return $cursor == self::$events[3]->id;
        })->once();
        $query = $this->applyQuery(2);
        $ids = array_map(fn ($item) => $item['id'], array_slice(self::$events, self::$perPage, self::$perPage));
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => 'id IN ('.implode(',', $ids).')',
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            $ids,
            array_map(fn ($item) => $item['id'], $query->execute())
        );
        $this->assertEquals(1, self::$route->getPage());
    }

    public function testBuildQueryRelatedToPage3NoCache(): void
    {
        self::$route->setRelatedTo('customer', (string) self::$customer->id);
        self::$cache->shouldReceive('get')->andReturn(false)->once();
        $query = $this->applyQuery(3);
        $ids = array_map(fn ($item) => $item['id'], array_slice(self::$events, self::$perPage * 2));
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => 'id IN ('.implode(',', $ids).')',
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            $ids,
            array_map(fn ($item) => $item['id'], $query->execute())
        );
        $this->assertEquals(1, self::$route->getPage());
    }

    public function testBuildQueryRelatedToPage2Cache(): void
    {
        self::$route->setRelatedTo('customer', (string) self::$customer->id);
        self::$cache->shouldReceive('get')->andReturn(self::$events[1]->id)->once();
        self::$cache->shouldReceive('set')->withArgs(function ($hash, $cursor) {
            return $cursor == self::$events[3]->id;
        })->once();
        $query = $this->applyQuery(2);
        $ids = array_map(fn ($item) => $item['id'], array_slice(self::$events, self::$perPage, self::$perPage));
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => 'id IN ('.implode(',', $ids).')',
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            $ids,
            array_map(fn ($item) => $item['id'], $query->execute())
        );
        $this->assertEquals(1, self::$route->getPage());
    }

    public function testBuildQueryRelatedToPage3Cache(): void
    {
        self::$route->setRelatedTo('customer', (string) self::$customer->id);
        self::$cache->shouldReceive('get')->andReturn(false)->once();
        $query = $this->applyQuery(3);
        $ids = array_map(fn ($item) => $item['id'], array_slice(self::$events, self::$perPage * 2));
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => 'id IN ('.implode(',', $ids).')',
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            $ids,
            array_map(fn ($item) => $item['id'], $query->execute())
        );
        $this->assertEquals(1, self::$route->getPage());
    }

    public function testBuildQueryRelatedToNoDataCache(): void
    {
        self::$route->setRelatedTo('invoice', (string) self::$customer->id);
        self::$cache->shouldReceive('get')->andReturn(false)->once();
        $query = $this->applyQuery();
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => '1=0',
        ], $query->getWhere());
        $this->assertEquals([], $query->getJoins());
        $this->assertEquals([], $query->getWith());
        $this->assertEmpty($query->execute());
        $this->assertEquals(1, self::$route->getPage());
    }

    public function testBuildQueryMultiplyPage3(): void
    {
        self::$apiCache->shouldReceive('getPaginationCursor')->once();
        self::$route->setRelatedTo('customer', (string) self::$customer->id);
        self::$route->setFrom('1');
        self::$cache->shouldReceive('get')->andReturn(false)->once();
        $query = $this->applyQuery(3);
        $ids = array_map(fn ($item) => $item['id'], array_slice(self::$events, self::$perPage * 2));
        $this->assertEquals([
            'tenant_id' => self::$company->id,
            0 => "(EventAssociations.object='customer' OR EventAssociations.object_type=1)",
            'EventAssociations.object_id' => self::$customer->id,
        ], $query->getWhere());
        $this->assertEquals([
            [
                EventAssociation::class,
                'Events.id',
                'event',
                'JOIN',
            ],
        ], $query->getJoins());
        $this->assertEquals(['user_id', 'associations'], $query->getWith());
        $this->assertEquals(
            $ids,
            array_map(fn ($item) => $item['id'], $query->execute())
        );
        $this->assertEquals(3, self::$route->getPage());
    }
}
