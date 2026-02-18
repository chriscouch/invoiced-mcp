<?php

namespace App\Tests\Chasing\Models;

use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\CompletedChasingStep;
use App\Tests\AppTestCase;

class CompletedChasingStepTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static CompletedChasingStep $step;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        self::$cadence = new ChasingCadence();
        self::$cadence->create([
            'name' => 'Test',
            'time_of_day' => 7,
            'steps' => [
                [
                    'name' => 'Email',
                    'schedule' => 'age:7',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                ],
            ],
        ]);
    }

    public function testCreate(): void
    {
        self::$step = new CompletedChasingStep();
        self::$step->customer_id = (int) self::$customer->id();
        self::$step->cadence_id = (int) self::$cadence->id();
        self::$step->chase_step_id = (int) self::$cadence->getSteps()[0]->id();
        self::$step->successful = true;
        self::$step->timestamp = (int) mktime(0, 0, 0, 8, 24, 2018);
        $this->assertTrue(self::$step->save());

        $this->assertEquals(self::$company->id(), self::$step->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $steps = CompletedChasingStep::all();

        $this->assertCount(1, $steps);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$step->id(),
            'customer_id' => self::$customer->id(),
            'cadence_id' => self::$cadence->id(),
            'chase_step_id' => self::$cadence->getSteps()[0]->id(),
            'successful' => true,
            'timestamp' => mktime(0, 0, 0, 8, 24, 2018),
            'message' => null,
        ];

        $this->assertEquals($expected, self::$step->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$step->delete());
    }
}
