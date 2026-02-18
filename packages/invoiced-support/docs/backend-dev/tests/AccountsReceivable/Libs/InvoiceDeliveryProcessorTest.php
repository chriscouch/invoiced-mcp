<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\InvoiceDeliveryProcessor;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ContactRole;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\InvoiceChasing\InvoiceChaseScheduleInspector;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class InvoiceDeliveryProcessorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$company->features->enable('smart_chasing');
        self::$company->features->enable('invoice_chasing');
    }

    /**
     * Tests that the InvoiceDeliveryProcessor deletes all scheduled sends
     * associated with an invoice delivery except for those in the current schedule.
     */
    public function testDeleteSends(): void
    {
        $delivery = $this->getInvoiceDelivery(self::$invoice);

        $ids = [
            InvoiceChaseScheduleInspector::generateChaseStepId(),
            InvoiceChaseScheduleInspector::generateChaseStepId(),
            InvoiceChaseScheduleInspector::generateChaseStepId(),
            InvoiceChaseScheduleInspector::generateChaseStepId(),
            InvoiceChaseScheduleInspector::generateChaseStepId(),
            InvoiceChaseScheduleInspector::generateChaseStepId(),
        ];

        // sends that do not exist in the current schedule (should be deleted)
        $send1 = $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[0]));
        $send2 = $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[1]));
        $send3 = $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[2]));
        $send4 = $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[3]), true); // should deleted despite being sent

        $tomorrow = CarbonImmutable::now()->addDays(1);
        // sends that exist in the current schedule (should not be deleted)
        $chaseSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 14, 'email' => true, 'sms' => true, 'letter' => true, 'date' => $tomorrow->toIso8601String()], $ids[4]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 16, 'email' => true, 'sms' => true, 'letter' => true, 'date' => $tomorrow->toIso8601String()], $ids[5]),
        ]);
        $delivery->chase_schedule = $chaseSchedule->toArrays();
        $sendA = $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[4]));
        $sendB = $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[5]));

        // sends that don't match the delivery reference scheme 'delivery:x:....' should not be canceled.
        $sendC = $this->createScheduledSend(self::$invoice, InvoiceChaseScheduleInspector::generateChaseStepId());

        $scheduler = new InvoiceDeliveryProcessor(self::getService('test.database'), self::getService('test.transaction_manager'));
        $scheduler->process($delivery);
        $this->assertNull(ScheduledSend::where('id', $send1->id())->oneOrNull());
        $this->assertNull(ScheduledSend::where('id', $send2->id())->oneOrNull());
        $this->assertNull(ScheduledSend::where('id', $send3->id())->oneOrNull());
        $this->assertNull(ScheduledSend::where('id', $send4->id())->oneOrNull());
        $this->assertNotNull(ScheduledSend::where('id', $sendA->id())->oneOrNull());
        $this->assertNotNull(ScheduledSend::where('id', $sendB->id())->oneOrNull());
        $this->assertNotNull(ScheduledSend::where('id', $sendC->id())->oneOrNull());

        // delete so other deliveries can be created w/ the same invoice in future tests
        $delivery->delete();
    }

    /**
     * Tests that steps which have been modified
     * - to remove some selected channel
     * will remove the scheduled send associated w/ said channel.
     * - to add some selected channel
     * will add a scheduled send associated w/ said channel.
     * - unmodified channel sends will have updated send_after
     * and parameter values.
     *
     * NOTE: All scheduled sends in this test have not been attempted
     * to be send at the time the delivery is processed. The testing
     * for already attempted sends is in the method
     * `testStepSendProcessingOnAttemptedSends`
     */
    public function testStepSendProcessing(): void
    {
        $delivery = $this->getInvoiceDelivery(self::$invoice);

        $chaseStep = new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12, 'email' => true, 'sms' => true, 'letter' => false]);
        // save the schedule to generate an id for the step
        $schedule = [$chaseStep->toArray()];
        $delivery->emails = 'test@example.com';
        $delivery->chase_schedule = $schedule;
        $delivery->saveOrFail();

        // should have updated parameters after test
        $emailSend = $this->createScheduledSend(self::$invoice, InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->get(0)), false, ScheduledSend::EMAIL_CHANNEL);
        // should be removed after test because chase step isn't set for sending letters
        $letterSend = $this->createScheduledSend(self::$invoice, InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->get(0)), false, ScheduledSend::LETTER_CHANNEL);

        // test
        $scheduler = new InvoiceDeliveryProcessor(self::getService('test.database'), self::getService('test.transaction_manager'));
        $scheduler->process($delivery);

        // assert email send updated
        $this->assertEquals([
            'to' => [
                [
                    'email' => 'test@example.com',
                ],
            ],
        ], $emailSend->refresh()->parameters);

        // assert letter send is deleted
        $this->assertNull(ScheduledSend::where('id', $letterSend->id())->oneOrNull());

        // assert sms send created
        $smsSend = ScheduledSend::where('invoice_id', self::$invoice->id())
            ->where('reference', InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->get(0)))
            ->where('channel', ScheduledSend::SMS_CHANNEL)
            ->oneOrNull();
        $this->assertInstanceOf(ScheduledSend::class, $smsSend);
    }

    /**
     * Tests that attempted sends associated with an updated invoice delivery
     * or updated invoice are not modified.
     */
    public function testSendProcessingOnAttemptedSends(): void
    {
        $delivery = $this->getInvoiceDelivery(self::$invoice);

        $chaseStep = new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12, 'email' => true, 'sms' => true, 'letter' => true]);
        // save the schedule to generate an id for the step
        $schedule = [$chaseStep->toArray()];
        $delivery->emails = 'test@example.com';
        $delivery->chase_schedule = $schedule;
        $delivery->saveOrFail();

        // sends should not be modified
        $emailSend = $this->createScheduledSend(self::$invoice, InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->get(0)), true, ScheduledSend::EMAIL_CHANNEL);
        $smsSend = $this->createScheduledSend(self::$invoice, InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->get(0)), true, ScheduledSend::SMS_CHANNEL);
        $letterSend = $this->createScheduledSend(self::$invoice, InvoiceDelivery::getSendReference($delivery, $delivery->getChaseSchedule()->get(0)), true, ScheduledSend::LETTER_CHANNEL);

        // test
        $scheduler = new InvoiceDeliveryProcessor(self::getService('test.database'), self::getService('test.transaction_manager'));
        $scheduler->process($delivery);

        // assert unchanged sends
        // NOTE: since the delivery emails value doesn't match the parameters,
        // these sends should be updated if they weren't already sent.
        $this->assertEquals($emailSend->toArray(), $emailSend->refresh()->toArray());
        $this->assertEquals($smsSend->toArray(), $smsSend->refresh()->toArray());
        $this->assertEquals($letterSend->toArray(), $letterSend->refresh()->toArray());
    }

    /**
     * Tests that the InvoiceDeliveryProcessor deletes all scheduled sends
     * associated with an invoice delivery except for those in the current schedule.
     */
    public function testReplacementIds(): void
    {
        self::hasInvoice();
        $delivery = $this->getInvoiceDelivery(self::$invoice);

        $ids = [
            InvoiceChaseScheduleInspector::generateChaseStepId(),
            InvoiceChaseScheduleInspector::generateChaseStepId(),
        ];

        $tomorrow = CarbonImmutable::now()->addDays(1);
        // sends that exist in the current schedule (should not be deleted)
        $chaseSchedule = new InvoiceChaseSchedule([
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 14, 'email' => true, 'sms' => true, 'letter' => true, 'date' => $tomorrow->toIso8601String()], $ids[0]),
            new InvoiceChaseStep(InvoiceChasingCadence::ABSOLUTE, ['hour' => 16, 'email' => true, 'sms' => true, 'letter' => true, 'date' => $tomorrow->toIso8601String()], $ids[1]),
        ]);
        $delivery->chase_schedule = $chaseSchedule->toArrays();
        $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[0]));
        $this->createScheduledSend(self::$invoice, $this->buildSendReference((string) $delivery->id(), $ids[1]));

        $scheduler = new InvoiceDeliveryProcessor(self::getService('test.database'), self::getService('test.transaction_manager'));
        $scheduler->process($delivery);

        $sends = self::getService('test.database')->createQueryBuilder()
            ->select('`id`, `channel`, `replacement_id`')
            ->from('ScheduledSends')
            ->andWhere('`invoice_id` = '.self::$invoice->id())
            ->orderBy('channel, ISNULL(replacement_id)')
            ->fetchAllAssociative();

        $this->assertCount(6, $sends);
        $this->assertEquals($sends[5]['replacement_id'], null);
        $this->assertEquals($sends[4]['replacement_id'], $sends[5]['id']);
        $this->assertEquals($sends[3]['replacement_id'], null);
        $this->assertEquals($sends[2]['replacement_id'], $sends[3]['id']);
        $this->assertEquals($sends[1]['replacement_id'], null);
        $this->assertEquals($sends[0]['replacement_id'], $sends[1]['id']);

        // delete so other deliveries can be created w/ the same invoice in future tests
        $delivery->delete();
    }

    public function testStepSendEmailRoleProcessing(): void
    {
        $contactRole2 = new ContactRole();
        $contactRole2->name = 'Test Role2';
        $contactRole2->saveOrFail();

        $contact = new Contact();
        $contact->customer = self::$customer;
        $contact->role = $contactRole2;
        $contact->email = 'test@test3.com';
        $contact->name = 'Another Role';
        $contact->saveOrFail();

        self::hasInvoice();
        $delivery = $this->getInvoiceDelivery(self::$invoice);

        $chaseStep = new InvoiceChaseStep(InvoiceChasingCadence::ON_ISSUE, ['hour' => 12, 'email' => true, 'sms' => false, 'letter' => false, 'role' => $contactRole2->id()]);
        // save the schedule to generate an id for the step
        $schedule = [$chaseStep->toArray()];
        $delivery->emails = 'test@example.com';
        $delivery->chase_schedule = $schedule;
        $delivery->saveOrFail();

        $this->assertNull(ScheduledSend::where('invoice_id', self::$invoice->id())
            ->where('channel', ScheduledSend::EMAIL_CHANNEL)
            ->oneOrNull());

        // test
        $scheduler = new InvoiceDeliveryProcessor(self::getService('test.database'), self::getService('test.transaction_manager'));
        $scheduler->process($delivery);

        // assert sms send created
        /** @var ScheduledSend $send */
        $send = ScheduledSend::where('invoice_id', self::$invoice->id())
            ->where('channel', ScheduledSend::EMAIL_CHANNEL)
            ->oneOrNull();

        $this->assertEquals([
                'role' => $contactRole2->id(),
            ],
            $send->refresh()->parameters);

        $this->assertInstanceOf(ScheduledSend::class, $send);
    }

    //
    // Helpers
    //

    /**
     * Gets an invoice delivery for an invoice.
     */
    private function getInvoiceDelivery(Invoice $invoice): InvoiceDelivery
    {
        $delivery = InvoiceDelivery::where('invoice_id', self::$invoice->id())
            ->oneOrNull();
        if ($delivery instanceof InvoiceDelivery) {
            $delivery->delete();
        }

        $delivery = new InvoiceDelivery();
        $delivery->invoice = $invoice;
        $delivery->saveOrFail();

        return $delivery;
    }

    /**
     * Creates a scheduled send w/ a reference.
     */
    private function createScheduledSend(Invoice $invoice, string $ref, bool $sent = false, int $channel = 0): ScheduledSend
    {
        $send = new ScheduledSend();
        $send->invoice = $invoice;
        $send->channel = $channel;
        $send->reference = $ref;
        $send->sent = $sent;
        $send->saveOrFail();

        return $send;
    }

    private function buildSendReference(string $deliveryId, string $stepId): string
    {
        return "delivery:$deliveryId:$stepId";
    }
}
