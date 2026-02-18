<?php

namespace App\Tests\PaymentProcessing;

use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\AutoPay;
use App\EntryPoint\QueueJob\AutoPayJob;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class AutoPayCronJobTest extends AppTestCase
{
    public function testExecute(): void
    {
        $connection = self::getService('test.database');
        $queue = \Mockery::mock(Queue::class);
        $statsd = \Mockery::mock(StatsdClient::class);

        $ids = [];

        $company1 = self::getTestDataFactory()->createCompany();
        self::$company = $company1;
        self::acceptsPaymentMethod(PaymentMethod::CHECK, null, 'Payment instructions...');
        self::acceptsPaymentMethod(PaymentMethod::CREDIT_CARD, null, 'Payment instructions...');
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->autopay = true;
        self::$invoice->next_payment_attempt = 1;
        self::$invoice->saveOrFail();
        $ids[] = $company1->id;

        $company2 = self::getTestDataFactory()->createCompany();
        self::$company = $company2;
        self::acceptsPaymentMethod(PaymentMethod::ACH, null, 'Payment instructions...');
        self::acceptsPaymentMethod(PaymentMethod::CREDIT_CARD, null, 'Payment instructions...');
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->autopay = true;
        self::$invoice->next_payment_attempt = 1;
        self::$invoice->saveOrFail();
        $ids[] = $company2->id;

        $company3 = self::getTestDataFactory()->createCompany();
        self::$company = $company3;
        self::acceptsPaymentMethod(PaymentMethod::CHECK, null, 'Payment instructions...');
        self::hasCustomer();
        self::hasInvoice();
        $ids[] = $company3->id;

        $job = new AutoPay($connection, $queue);
        $job->setStatsd($statsd);

        $queue->shouldReceive('enqueue')
            ->with(AutoPayJob::class, [
                'tenant_id' => $ids[0],
            ], QueueServiceLevel::Batch)
            ->once();
        $queue->shouldReceive('enqueue')
            ->with(AutoPayJob::class, [
                'tenant_id' => $ids[1],
            ], QueueServiceLevel::Batch)
            ->once();
        $statsd->shouldReceive('gauge')
            ->with('cron.task_queue_size', 2, 1, ['cron_job' => $job::getName()]);
        $statsd->shouldReceive('updateStats')
            ->with('cron.processed_task', 2, 1, ['cron_job' => $job::getName()]);

        $job->execute(new Run());
        // clean up
        $company1->delete();
        $company2->delete();
        $company3->delete();
    }
}
