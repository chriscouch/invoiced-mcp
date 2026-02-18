<?php

namespace App\Tests\Chasing\ValueObjects;

use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Tests\AppTestCase;

class InvoiceChaseScheduleTest extends AppTestCase
{
    public function testIntersect(): void
    {
        $schedule1 = InvoiceChaseSchedule::fromArrays([
            [
                'trigger' => 0,
                'options' => [
                    'hour' => 7,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ]);
        $this->assertNull($schedule1->toArray()[0]->getId());

        $schedule2 = InvoiceChaseSchedule::fromArrays([
            [
                'id' => 'test_merge_id',
                'trigger' => 0,
                'options' => [
                    'hour' => 7,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ]);

        $schedule1->intersect($schedule2);

        $this->assertEquals('test_merge_id', $schedule1->toArray()[0]->getId());
    }
}
