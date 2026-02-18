<?php

namespace App\Tests\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\InvoiceChasing\InvoiceChaseScheduleInspector;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Core\Utils\RandomString;
use App\Tests\AppTestCase;

class InvoiceChaseScheduleInspectorTest extends AppTestCase
{
    public static InvoiceDelivery $delivery;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::$company->features->enable('smart_chasing');
        self::$company->features->enable('invoice_chasing');

        self::$delivery = new InvoiceDelivery();
        self::$delivery->invoice = self::$invoice;
        self::$delivery->saveOrFail();
    }

    /**
     * Tests that invoice chase steps that are removed from a schedule
     * do not appear in the diff handler's return value.
     */
    public function testScheduleRemovals(): void
    {
        // NOTE: Removals do not make use of the step options so they're left
        // empty here.
        $oldSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, [], $this->generateId()),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, [], $this->generateId()),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, [], $this->generateId()),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, [], $this->generateId()),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, [], $this->generateId()),
        ]);
        $this->assertEquals([], InvoiceChaseScheduleInspector::inspect(self::$delivery, $oldSchedule, new InvoiceChaseSchedule([]))->toArray());
    }

    /**
     * Tests that new steps (steps w/o an id) have a generated id in
     * the diff handler's return value.
     */
    public function testScheduleAdditions(): void
    {
        // NOTE: Removals do not make use of the step options so they're left
        // empty here.
        $newSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, []),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, []),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, []),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, []),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, []),
        ]);

        $result = InvoiceChaseScheduleInspector::inspect(self::$delivery, new InvoiceChaseSchedule([]), $newSchedule);
        foreach ($result as $step) {
            $this->assertNotNull($step->getId());
        }

        // Test that user given ids are overwritten
        $newSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, [], '1'),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, [], '2'),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, [], '3'),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, [], '4'),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, [], '5'),
        ]);

        $result = InvoiceChaseScheduleInspector::inspect(self::$delivery, new InvoiceChaseSchedule([]), $newSchedule);
        foreach ($result as $step) {
            $this->assertEquals(InvoiceChaseScheduleInspector::ID_LENGTH, strlen((string) $step->getId()));
        }
    }

    /**
     * Tests that steps w/ the same ids in the new and old schedules
     * but have different options (i.e modified steps) are given new
     * ids in the diff handler's return value.
     */
    public function testScheduleModifications(): void
    {
        $oldIds = [
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
        ];

        $oldSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, [], $oldIds[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, [], $oldIds[1]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, [], $oldIds[2]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, [], $oldIds[3]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, [], $oldIds[4]),
        ]);

        // This schedule tests that a change in trigger w/o option changes results in a new id
        $newSchedule1 = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, [], $oldIds[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, [], $oldIds[1]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, [], $oldIds[2]),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, [], $oldIds[3]),
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, [], $oldIds[4]),
        ]);
        // This schedule tests that a change on options w/o a trigger change results in a new id
        $newSchedule2 = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 12], $oldIds[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12], $oldIds[1]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 12], $oldIds[2]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 12], $oldIds[3]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 12], $oldIds[4]),
        ]);

        $result1 = InvoiceChaseScheduleInspector::inspect(self::$delivery, $oldSchedule, $newSchedule1);
        foreach ($result1 as $step) {
            $this->assertNotNull($step->getId());
            $this->assertTrue(in_array($step->getId(), $oldIds));
        }

        $result2 = InvoiceChaseScheduleInspector::inspect(self::$delivery, $oldSchedule, $newSchedule2);
        foreach ($result2 as $step) {
            $this->assertNotNull($step->getId());
            $this->assertTrue(in_array($step->getId(), $oldIds));
        }
    }

    /**
     * Tests that unmodified steps (same id, same trigger, same options) have
     * the same id in the diff handler's return value.
     */
    public function testUnmodifiedSteps(): void
    {
        $ids = [
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
        ];

        $oldSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 12], $ids[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12], $ids[1]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 12], $ids[2]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 12], $ids[3]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 12], $ids[4]),
        ]);
        $newSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 12], $ids[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12], $ids[1]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 12], $ids[2]),
            new InvoiceChaseStep(InvoiceChasingCadence::REPEATER, ['hour' => 12], $ids[3]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 12], $ids[4]),
        ]);

        $result = InvoiceChaseScheduleInspector::inspect(self::$delivery, $oldSchedule, $newSchedule);
        $this->assertEquals($oldSchedule->toArray(), $result->toArray());
        $this->assertEquals($newSchedule->toArray(), $result->toArray());
    }

    /**
     * Tests a mix of removals, modifications, additions to the schedule.
     */
    public function testScheduleVariation(): void
    {
        $ids = [
            $this->generateId(),
            $this->generateId(),
            $this->generateId(),
        ];

        $oldSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::BEFORE_DUE, ['hour' => 12], $ids[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12], $ids[1]),
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 12], $ids[2]),
        ]);
        $newSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 13], $ids[1]), // modified
            new InvoiceChaseStep(InvoiceChasingCadence::AFTER_DUE, ['hour' => 12], $ids[2]), // unmodified
        ]);

        $result = InvoiceChaseScheduleInspector::inspect(self::$delivery, $oldSchedule, $newSchedule);
        // test removal
        $this->assertEquals(2, $result->size());

        // test modified
        $this->assertEquals($oldSchedule->get(1)->getId(), $result->get(0)->getId());
        $this->assertEquals($oldSchedule->get(1)->getTrigger(), $result->get(0)->getTrigger());
        $this->assertEquals(['hour' => 13], $result->get(0)->getOptions());

        // test unmodified
        $this->assertEquals($ids[2], $result->get(1)->getId());
        $this->assertEquals($oldSchedule->get(2), $result->get(1));
        $this->assertEquals($newSchedule->get(1), $result->get(1));
    }

    //
    // Helpers
    //

    /**
     * Generates a uuid for the purpose of using it for a step id.
     */
    private function generateId(): string
    {
        return RandomString::generate(InvoiceChaseScheduleInspector::ID_LENGTH, RandomString::CHAR_ALNUM);
    }
}
