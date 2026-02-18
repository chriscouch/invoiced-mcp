<?php

namespace App\Tests\Core\Queue\EventSubscriber;

use App\Core\Queue\Events\BeforePerformEvent;
use App\Core\Queue\EventSubscriber\ResqueConcurrencySubscriber;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\MemberACLExportJob;
use App\Tests\AppTestCase;
use Exception;
use Mockery;
use Monolog\Logger;
use Resque_Job;
use Resque_Job_DontPerform;
use Symfony\Component\Semaphore\Exception\SemaphoreAcquiringException;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\RedisStore;

/**
 * Controls resque job concurrency when configured.
 */
class ResqueConcurrencySubscriberTest extends AppTestCase
{
    /**
     * @throws Resque_Job_DontPerform if the job should be skipped
     */
    public function testBeforePerform(): void
    {
        $store = Mockery::mock(RedisStore::class);
        $store->shouldReceive('exists');
        $semaphoreFactory = new SemaphoreFactory($store);
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueueAt');

        $event = new BeforePerformEvent(new Resque_Job(QueueServiceLevel::Batch->value, [
            'args' => [
                [
                    '_job_class' => MemberACLExportJob::class,
                    'tenant_id' => 1,
                ],
            ],
        ]));

        $subscriber = new ResqueConcurrencySubscriber($semaphoreFactory, $queue);
        $logger = Mockery::mock(Logger::class);
        $subscriber->setLogger($logger);

        $store->shouldReceive('save')->andReturn(true)->once();
        // cancelled because of semaphore
        $subscriber->beforePerform($event);
        $logger->shouldReceive('notice')->once();
        $store->shouldReceive('save')->andThrow(new SemaphoreAcquiringException(new Key(QueueServiceLevel::Batch->value, 1), 'test'))->once();
        try {
            $subscriber->beforePerform($event);
            throw new Exception('Exception not thrown');
        } catch (Resque_Job_DontPerform) {
        }
    }
}
