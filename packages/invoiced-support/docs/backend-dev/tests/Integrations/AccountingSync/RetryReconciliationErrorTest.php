<?php

namespace App\Tests\Integrations\AccountingSync;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\AccountingWriteJob;
use App\EntryPoint\QueueJob\IntacctSyncJob;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use Mockery;

class RetryReconciliationErrorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getRetry(Queue $queue): RetryReconciliationError
    {
        return new RetryReconciliationError($queue);
    }

    public function testRetryRead(): void
    {
        $error = new ReconciliationError();
        $error->setIntegration(IntegrationType::Intacct);
        $error->accounting_id = '1234';
        $error->object = 'invoice';
        $error->message = 'test';
        $error->retry_context = (object) ['test' => 'arg'];
        $error->saveOrFail();

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')
            ->withArgs([
                IntacctSyncJob::class,
                ['test' => 'arg', 'tenant_id' => self::$company->id, 'single_sync' => true],
                QueueServiceLevel::Batch,
            ])
            ->once();

        $retry = $this->getRetry($queue);

        $retry->retry($error);
    }

    public function testRetryWrite(): void
    {
        $error = new ReconciliationError();
        $error->setIntegration(IntegrationType::Intacct);
        $error->object = 'invoice';
        $error->object_id = 1234;
        $error->message = 'test';
        $error->retry_context = (object) ['e' => 'model.updated'];
        $error->saveOrFail();

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')
            ->withArgs([
                AccountingWriteJob::class,
                [
                    'tenant_id' => self::$company->id,
                    'class' => Invoice::class,
                    'id' => '1234',
                    'accounting_system' => 1,
                    'eventName' => 'model.updated',
                ],
            ])
            ->once();

        $retry = $this->getRetry($queue);

        $retry->retry($error);
    }
}
