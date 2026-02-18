<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\PaymentProcessing\ValueObjects\RetrySchedule;
use InvalidArgumentException;

class RetryScheduleTest extends AbstractScheduleTest
{
    public function testGetSchedule(): void
    {
        $schedule = $this->_getScheduler([1, 2.2, 3]);
        $this->assertEquals([1, 2, 3], $schedule->getSchedule());
    }

    public function testIsValid(): void
    {
        $this->assertTrue(RetrySchedule::validate([1, 2, 3, 4]));
        $this->assertFalse(RetrySchedule::validate([-1, 8, 3]));
        $this->assertTrue(RetrySchedule::validate([8, 9, 10]));
        $this->assertFalse(RetrySchedule::validate([1, 8, 11]));
        $this->assertFalse(RetrySchedule::validate([1, 3, 5, 7, 9]));
        $this->assertTrue(RetrySchedule::validate([1, 2, 3]));
        $this->assertFalse(RetrySchedule::validate([1, 2, 3, 11]));
    }

    public function testNextInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $schedule = $this->_getScheduler([1, 2, 3]);
        $schedule->next();
    }

    public function testNext(): void
    {
        $scheduleArray = [1, 2, 3];
        $last = time();
        $schedule = $this->_getScheduler($scheduleArray, $last, 1);
        $this->assertEquals($last + 86400, $schedule->next());
        $schedule = $this->_getScheduler($scheduleArray, $last, 2);
        $this->assertEquals($last + 86400 * 2, $schedule->next());
        $schedule = $this->_getScheduler($scheduleArray, $last, 3);
        $this->assertEquals($last + 86400 * 3, $schedule->next());

        $schedule = $this->_getScheduler($scheduleArray, $last, 4);
        $this->assertNull($schedule->next());
        $schedule = $this->_getScheduler($scheduleArray, $last, 5);
        $this->assertNull($schedule->next());
        $schedule = $this->_getScheduler($scheduleArray, $last, 6);
        $this->assertNull($schedule->next());
    }

    private function _getScheduler(array $scheduleArray, int $nextPaymentAttempt = null, int $attemptCount = 0): RetrySchedule
    {
        $invoice = $this->getInvoice();
        $invoice->next_payment_attempt = $nextPaymentAttempt;
        $invoice->attempt_count = $attemptCount;
        $schedule = new RetrySchedule($invoice, $scheduleArray);

        return $schedule;
    }
}
