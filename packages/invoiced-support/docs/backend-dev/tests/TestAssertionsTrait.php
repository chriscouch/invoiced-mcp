<?php

namespace App\Tests;

use App\Core\Orm\Model;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;

trait TestAssertionsTrait
{
    /**
     * Checks that an integer is within an inclusive range.
     */
    protected function assertBetween(int $value, int $min, int $max, string $message = ''): void
    {
        $this->assertThat(
            $value,
            $this->logicalAnd(
                $this->greaterThanOrEqual($min),
                $this->lessThanOrEqual($max)
            ),
            $message
        );
    }

    /**
     * Compares two UNIX timestamps with a more helpful failure condition.
     */
    protected function assertTimestampsEqual(int $a, int $b, string $message = ''): void
    {
        $this->assertEquals(date('c', $a), date('c', $b), $message);
    }

    /**
     * Checks that a UNIX timestamp matches a given date in YYYY-MM-DD format.
     */
    protected function assertEqualsDate(string $date, int $timestamp, string $message = ''): void
    {
        $this->assertEquals($date, date('Y-m-d', $timestamp), $message);
    }

    /**
     * Checks that there is an event of the specified type for a given model.
     */
    protected function assertHasEvent(Model $model, EventType $eventType, int $expectedCount = 1): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $numEvents = Event::where('type_id', $eventType->toInteger())
            ->where('object_type_id', ObjectType::fromModel($model)->value)
            ->where('object_id', $model)
            ->count();
        $this->assertEquals($expectedCount, $numEvents);
    }
}
