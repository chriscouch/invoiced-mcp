<?php

namespace App\Tests\Chasing\Api;

use App\Chasing\Api\MassAssignLateFeeScheduleRoute;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class MassAssignLateFeeScheduleTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasLateFeeSchedule();
    }

    public function testRouteFailed(): void
    {
        $request = new Request();
        $request->attributes->set('model_id', self::$lateFeeSchedule->id);

        $route = new MassAssignLateFeeScheduleRoute();

        $this->expectException(InvalidRequest::class);
        self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
    }

    public function testRouteNotFond(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('No such customer: -1');

        $this->assertNull(self::$customer->late_fee_schedule);

        $request = new Request();
        $request->attributes->set('model_id', self::$lateFeeSchedule->id);

        $route = new MassAssignLateFeeScheduleRoute();

        $request->request->set('customers', [-1]);
        self::getService('test.api_runner')->run($route, $request);
    }

    public function testRouteSuccess(): void
    {
        $request = new Request();
        $request->attributes->set('model_id', self::$lateFeeSchedule->id);
        $request->request->set('customers', [self::$customer->id]);

        $route = new MassAssignLateFeeScheduleRoute();

        self::getService('test.api_runner')->run($route, $request);

        self::$customer->refresh();
        $this->assertEquals(self::$lateFeeSchedule->id, self::$customer->late_fee_schedule_id);
    }
}
