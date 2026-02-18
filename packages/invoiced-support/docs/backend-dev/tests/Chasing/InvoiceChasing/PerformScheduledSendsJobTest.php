<?php

namespace App\Tests\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\Enums\ChasingChannelEnum;
use App\Chasing\Models\ChasingStatistic;
use App\Chasing\Models\InvoiceChasingCadence;
use App\EntryPoint\QueueJob\PerformScheduledSendsJob;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;

class PerformScheduledSendsJobTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
        self::hasCustomer();
    }

    public function testPerform(): void
    {
        /** @var PerformScheduledSendsJob $service */
        $service = AppTestCase::getService('test.scheduled_sends_job');

        $cadence = new InvoiceChasingCadence();
        $cadence->name = 'Chasing Cadence';
        $cadence->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $cadence->saveOrFail();

        $firstDelivery = $this->createDelivery($cadence);
        $this->createScheduledSend(ScheduledSend::EMAIL_CHANNEL, "invoice:{$firstDelivery->id}:{$firstDelivery->invoice_id}");
        $secondDelivery = $this->createDelivery();
        $this->createScheduledSend(ScheduledSend::EMAIL_CHANNEL, "invoice:{$secondDelivery->id}:{$secondDelivery->invoice_id}");
        $delivery = $this->createDelivery($cadence);
        // account doesnt support sms
        $this->createScheduledSend(ScheduledSend::SMS_CHANNEL, "invoice:{$delivery->id}:{$delivery->invoice_id}");
        $this->createScheduledSend(ScheduledSend::EMAIL_CHANNEL, "invoice:-1:{$delivery->invoice_id}", true);
        $this->createScheduledSend(ScheduledSend::EMAIL_CHANNEL, '');
        $this->createScheduledSend(ScheduledSend::EMAIL_CHANNEL, "invoice:-1:{$delivery->invoice_id}");

        $service->perform();

        /** @var ChasingStatistic[] $statistics */
        $statistics = ChasingStatistic::query()->execute();
        $this->assertCount(2, $statistics);
        $this->assertEquals(ChasingChannelEnum::Email->value, $statistics[0]->channel);
        $this->assertEquals($firstDelivery->invoice_id, $statistics[0]->invoice_id);
        $this->assertEquals($cadence->id, $statistics[0]->invoice_cadence_id);
        $this->assertEquals(ChasingChannelEnum::Email->value, $statistics[1]->channel);
        $this->assertEquals($secondDelivery->invoice_id, $statistics[1]->invoice_id);
        $this->assertEquals(null, $statistics[1]->invoice_cadence_id);

        $this->createScheduledSend(ScheduledSend::EMAIL_CHANNEL, "invoice:{$firstDelivery->id}:{$firstDelivery->invoice_id}");
        $service->perform();
        $statistics = ChasingStatistic::where('invoice_id', $firstDelivery->invoice_id)->execute();
        $this->assertCount(2, $statistics);
        $this->assertFalse($statistics[0]->payment_responsible);
        $this->assertEquals(1, $statistics[0]->attempts);
        $this->assertNull($statistics[1]->payment_responsible);
        $this->assertEquals(2, $statistics[1]->attempts);
    }

    private function createDelivery(?InvoiceChasingCadence $cadence = null): InvoiceDelivery
    {
        self::hasInvoice();
        $delivery = new InvoiceDelivery();
        $delivery->invoice = self::$invoice;
        if ($cadence) {
            $delivery->applyCadence($cadence);
        }
        $delivery->saveOrFail();

        return $delivery;
    }

    private function createScheduledSend(int $channel, string $reference, bool $skipped = false): void
    {
        $send = new ScheduledSend();
        $send->channel = $channel;
        $send->invoice = self::$invoice;
        $send->skipped = $skipped;
        $send->reference = $reference;
        $send->saveOrFail();
    }
}
