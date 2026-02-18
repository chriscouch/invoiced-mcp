<?php

namespace App\Tests\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\InvoiceChasing\InvoiceChaseTimelineBuilder;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class InvoiceChaseTimelineBuilderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    /**
     * Tests that the datetime created for the "on issue" trigger is correct.
     */
    public function testOnIssueDate(): void
    {
        $builder = new InvoiceChaseTimelineBuilder();
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12]),
        ]);

        self::$invoice->date = CarbonImmutable::now()->addDays(2)->unix();
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());

        $expectedDate = CarbonImmutable::now()->addDays(2)
            ->setHour(12)
            ->setMinutes(0)
            ->setSeconds(0)
            ->setMilliseconds(0);
        $this->assertEquals($expectedDate, $segment->getDates()[0]);
    }

    /**
     * Tests that the datetime created for the "before due" trigger is correct.
     */
    public function testBeforeDueDate(): void
    {
        $builder = new InvoiceChaseTimelineBuilder();
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 10, 'days' => 2]),
        ]);

        // test w/o invoice due date
        self::$invoice->date = (int) mktime(0, 0, 0, 11, 3, 2021);
        self::$invoice->due_date = null;
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());
        // Dates earlier than issue date are not allowed. The expected time is the issue date
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(10, 0, 0, 11, 3, 2021)), $segment->getDates()[0]);

        // test w/ invoice due date
        self::$invoice->due_date = (int) mktime(0, 0, 0, 11, 10, 2021);
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(10, 0, 0, 11, 8, 2021)), $segment->getDates()[0]);

        // test w/ invoice due date and step earlier than issue date
        self::$invoice->due_date = (int) mktime(0, 0, 0, 11, 4, 2021);
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(10, 0, 0, 11, 3, 2021)), $segment->getDates()[0]);
    }

    /**
     * Tests that the datetime created for the "after due" trigger is correct.
     */
    public function testAfterDueDate(): void
    {
        $builder = new InvoiceChaseTimelineBuilder();
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 14, 'days' => 5]),
        ]);

        // test w/o invoice due date
        self::$invoice->date = (int) mktime(0, 0, 0, 11, 3, 2021);
        self::$invoice->due_date = null;
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(14, 0, 0, 11, 8, 2021)), $segment->getDates()[0]);

        // test w/ invoice due date
        self::$invoice->due_date = (int) mktime(0, 0, 0, 11, 10, 2021);
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(14, 0, 0, 11, 15, 2021)), $segment->getDates()[0]);
    }

    /**
     * Tests that the datetime created for the "absolute" trigger is correct.
     */
    public function testAbsoluteDate(): void
    {
        $builder = new InvoiceChaseTimelineBuilder();
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, [
                'hour' => 8,
                'date' => CarbonImmutable::createFromTimestamp((int) mktime(0, 0, 0, 11, 20, 2021)),
            ]),
        ]);

        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(8, 0, 0, 11, 20, 2021)), $segment->getDates()[0]);
    }

    /**
     * Tests that the datetimes created for the "repeater" trigger are correct.
     */
    public function testRepeaterDates(): void
    {
        $builder = new InvoiceChaseTimelineBuilder();
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 10, 'days' => 5, 'repeats' => 4]),
        ]);

        $now = CarbonImmutable::now();

        // test w/o invoice due date (due date is in the past)
        self::$invoice->date = (int) mktime(0, 0, 0, 11, 5, 2021);
        self::$invoice->due_date = null;
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(4, $segment->getDates());
        // since the date is in the past, these should be based off the current date
        $this->assertEquals($now->addDays(5)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[0]);
        $this->assertEquals($now->addDays(10)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[1]);
        $this->assertEquals($now->addDays(15)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[2]);
        $this->assertEquals($now->addDays(20)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[3]);

        // test w/ invoice due date (use current year)
        $dueDate = $now->addDays(1);
        self::$invoice->due_date = $dueDate->unix();
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $segment = $timeline->current();
        $this->assertEquals(1, $timeline->size());
        $this->assertCount(4, $segment->getDates());
        $this->assertEquals($dueDate->addDays(5)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[0]);
        $this->assertEquals($dueDate->addDays(10)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[1]);
        $this->assertEquals($dueDate->addDays(15)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[2]);
        $this->assertEquals($dueDate->addDays(20)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[3]);

        // test w/ non-repeating options included (non repeating is before issue date so repetitions should start from the issue date)
        self::$invoice->date = (int) mktime(0, 0, 0, 11, 5, 2021);
        self::$invoice->due_date = null;
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 10, 'days' => 4]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 10, 'days' => 5, 'repeats' => 4]),
        ]);
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $timeline->next(); // the repeater should appear last
        $segment = $timeline->current();
        $this->assertEquals(2, $timeline->size());
        $this->assertCount(4, $segment->getDates());
        $this->assertEquals($now->addDays(5)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[0]);
        $this->assertEquals($now->addDays(10)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[1]);
        $this->assertEquals($now->addDays(15)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[2]);
        $this->assertEquals($now->addDays(20)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[3]);

        // test w/ non-repeating options included (non repeating is after issue date so repetitions should start after the "after_due" step).
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 10, 'days' => 5]), // step ends on 11/10/2021 so first repeater should start on 11/15/2021
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 10, 'days' => 5, 'repeats' => 4]),
        ]);
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));
        $timeline->next(); // the repeater should appear last
        $segment = $timeline->current();
        $this->assertEquals(2, $timeline->size());
        $this->assertCount(4, $segment->getDates());
        $this->assertEquals($now->addDays(5)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[0]);
        $this->assertEquals($now->addDays(10)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[1]);
        $this->assertEquals($now->addDays(15)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[2]);
        $this->assertEquals($now->addDays(20)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[3]);
    }

    /**
     * Tests the the timeline is in chronological order.
     */
    public function testTimelineOrder(): void
    {
        $builder = new InvoiceChaseTimelineBuilder();
        $schedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 10, 'days' => 4]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 10, 'days' => 2]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 10, 'date' => CarbonImmutable::createFromTimestamp((int) mktime(0, 0, 0, 11, 15, 2021))]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 10, 'days' => 5, 'repeats' => 2]),
        ]);

        // test w/o invoice due date
        self::$invoice->date = (int) mktime(0, 0, 0, 11, 2, 2021);
        self::$invoice->due_date = (int) mktime(0, 0, 0, 11, 8, 2021);
        $timeline = $builder->build($this->mockDelivery(self::$invoice, $schedule));

        // test before due
        $segment = $timeline->current();
        $this->assertEquals(InvoiceChasingCadence::BEFORE_DUE, $segment->getChaseStep()->getTrigger());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(10, 0, 0, 11, 4, 2021)), $segment->getDates()[0]);

        // test after due
        $timeline->next();
        $segment = $timeline->current();
        $this->assertEquals(InvoiceChasingCadence::AFTER_DUE, $segment->getChaseStep()->getTrigger());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(10, 0, 0, 11, 10, 2021)), $segment->getDates()[0]);

        // test absolute date
        $timeline->next();
        $segment = $timeline->current();
        $this->assertEquals(InvoiceChasingCadence::ABSOLUTE, $segment->getChaseStep()->getTrigger());
        $this->assertCount(1, $segment->getDates());
        $this->assertEquals(CarbonImmutable::createFromTimestamp((int) mktime(10, 0, 0, 11, 15, 2021)), $segment->getDates()[0]);

        // test repeater
        $now = CarbonImmutable::now();
        $timeline->next();
        $segment = $timeline->current();
        $this->assertEquals(InvoiceChasingCadence::REPEATER, $segment->getChaseStep()->getTrigger());
        $this->assertCount(2, $segment->getDates());
        $this->assertEquals($now->addDays(5)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[0]);
        $this->assertEquals($now->addDays(10)->setHour(10)->setMinutes(0)->setSeconds(0)->setMilliseconds(0), $segment->getDates()[1]);

        // should be the end of the timeline
        $timeline->next();
        $this->assertFalse($timeline->valid());
    }

    private function mockDelivery(Invoice $invoice, InvoiceChaseSchedule $schedule): InvoiceDelivery
    {
        $delivery = new InvoiceDelivery();
        $delivery->invoice = $invoice;
        $delivery->chase_schedule = $schedule->toArrays();

        return $delivery;
    }
}
