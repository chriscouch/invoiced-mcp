<?php

namespace App\Tests\SubscriptionBilling\Api;

use App\SubscriptionBilling\ListQueryBuilders\SubscriptionListQueryBuilder;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;

class ListSubscriptionsRouteTest extends AppTestCase
{
    public function testBuild(): void
    {
        self::hasCompany();
        $route = new SubscriptionListQueryBuilder(self::getService('test.database'));
        $route->setQueryClass(Subscription::class);
        $route->setCompany(self::$company);
        $route->setOptions([]);
        $route->initialize();

        $query = $route->getBuildQuery();
        $this->assertEquals([
            ['canceled', false, '='],
            ['finished', false, '='],
            'tenant_id' => self::$company->id,
        ], $query->getWhere());

        $route->setOptions([
            'canceled' => '1',
            'finished' => '1',
            'contract' => '1',
        ]);
        $route->initialize();
        $query = $route->getBuildQuery();
        $this->assertEquals([
            ['canceled', true, '='],
            ['finished', true, '='],
            ['cycles', 0, '>'],
            'tenant_id' => self::$company->id,
        ], $query->getWhere());

        $route->setOptions([
            'contract' => '0',
        ]);
        $route->initialize();
        $query = $route->getBuildQuery();
        $this->assertEquals([
            ['canceled', false, '='],
            'tenant_id' => self::$company->id,
            ['finished', false, '='],
            ['cycles', 0, '='],
        ], $query->getWhere());

        $route->setOptions([
            'all' => '1',
        ]);
        $route->initialize();
        $query = $route->getBuildQuery();
        $this->assertEquals([
            'tenant_id' => self::$company->id,
        ], $query->getWhere());
    }
}
