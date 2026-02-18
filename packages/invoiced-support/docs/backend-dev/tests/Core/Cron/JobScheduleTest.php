<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\Events\ScheduleRunBeginEvent;
use App\Core\Cron\Events\ScheduleRunFinishedEvent;
use App\Core\Cron\Libs\JobSchedule;
use App\Core\Cron\Models\CronJob;
use App\Tests\AppTestCase;
use App\Tests\Core\Cron\Jobs\FailJob;
use App\Tests\Core\Cron\Jobs\LockedJob;
use App\Tests\Core\Cron\Jobs\SuccessJob;
use App\Tests\Core\Cron\Jobs\SuccessSkipJob;
use App\Tests\Core\Cron\Jobs\SuccessWithUrlJob;
use Mockery;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class JobScheduleTest extends AppTestCase
{
    private static array $jobs = [
        [
            'class' => SuccessWithUrlJob::class,
            'schedule' => '0 0 * * *',
        ],
        [
            'class' => SuccessJob::class,
            'schedule' => '* * * * *',
        ],
        [
            'class' => SuccessSkipJob::class,
            'schedule' => '* * * * *',
        ],
        [
            'class' => LockedJob::class,
            'schedule' => '* * * * *',
        ],
        [
            'class' => FailJob::class,
            'schedule' => '* * * * *',
        ],
    ];

    public static Mockery\MockInterface $jobLocator;
    private static LockFactory $lockFactory;
    private static ScheduleRunBeginEvent $beginEvent;
    private static ScheduleRunFinishedEvent $finishedEvent;

    public static function setUpBeforeClass(): void
    {
        self::getService('test.database')->executeStatement('DELETE FROM CronJobs WHERE id LIKE "test%"');

        self::$jobLocator = Mockery::mock(ServiceLocator::class);

        $store = new FlockStore(sys_get_temp_dir());
        self::$lockFactory = new LockFactory($store);

        $lock = self::$lockFactory->createLock('cron.test.locked', 100);
        $lock->acquire();
    }

    private function getSchedule(): JobSchedule
    {
        return new JobSchedule(self::$jobs, self::$jobLocator, self::$lockFactory, []);
    }

    public function testGetAllJobs(): void
    {
        $this->assertEquals(self::$jobs, $this->getSchedule()->getAllJobs());
    }

    public function testGetScheduledJobs(): void
    {
        $jobs = $this->getSchedule()->getScheduledJobs();
        $jobs = iterator_to_array($jobs);
        $this->assertCount(5, $jobs);

        $this->assertInstanceOf(CronJob::class, $jobs[0]['model']);
        $this->assertEquals('test.success_with_url', $jobs[0]['model']->id);

        $this->assertInstanceOf(CronJob::class, $jobs[1]['model']);
        $this->assertEquals('test.success', $jobs[1]['model']->id);

        $this->assertInstanceOf(CronJob::class, $jobs[2]['model']);
        $this->assertEquals('test.success.skip', $jobs[2]['model']->id);

        $this->assertInstanceOf(CronJob::class, $jobs[3]['model']);
        $this->assertEquals('test.locked', $jobs[3]['model']->id);

        $this->assertInstanceOf(CronJob::class, $jobs[4]['model']);
        $this->assertEquals('test.failed', $jobs[4]['model']->id);
    }

    public function testRunScheduled(): void
    {
        self::$jobLocator->shouldReceive('get')
            ->withArgs([SuccessJob::class])
            ->andReturn(new SuccessJob());

        self::$jobLocator->shouldReceive('get')
            ->withArgs([SuccessSkipJob::class])
            ->andReturn(new SuccessSkipJob());

        self::$jobLocator->shouldReceive('get')
            ->withArgs([LockedJob::class])
            ->andReturn(new LockedJob());

        self::$jobLocator->shouldReceive('get')
            ->withArgs([FailJob::class])
            ->andReturn(new FailJob());

        self::$jobLocator->shouldReceive('get')
            ->withArgs([SuccessWithUrlJob::class])
            ->andReturn(new SuccessWithUrlJob());

        $output = new SymfonyStyle(new ArgvInput(), new NullOutput());

        $schedule = $this->getSchedule();
        $schedule->listen(ScheduleRunBeginEvent::NAME, function (ScheduleRunBeginEvent $event) {
            JobScheduleTest::$beginEvent = $event;
        });
        $schedule->listen(ScheduleRunFinishedEvent::NAME, function (ScheduleRunFinishedEvent $event) {
            JobScheduleTest::$finishedEvent = $event;
        });

        $subscriber = new TestEventSubscriber();
        $schedule->subscribe($subscriber);

        $this->assertFalse($schedule->runScheduled($output));

        $this->assertInstanceOf(ScheduleRunBeginEvent::class, self::$beginEvent);
        $this->assertInstanceOf(ScheduleRunFinishedEvent::class, self::$finishedEvent);

        // running the schedule should clear everything
        $jobs = $schedule->getScheduledJobs();
        $this->assertNull($jobs->current());
    }

    public function testRunScheduledParallelExecution(): void
    {
        self::getService('test.database')->executeStatement('TRUNCATE CronJobs');

        $schedule = $this->getSchedule();
        $jobs = $schedule->getScheduledJobs();

        // testing a job that has already executed
        $job = new CronJob();
        $job->id = 'test.success.skip';
        $job->last_ran = time() + 3600;
        $job->saveOrFail();

        // testing the lock
        $lock = self::$lockFactory->createLock('cron.test.locked', 100);
        $lock->acquire();

        $this->assertEquals('test.success_with_url', $jobs->current()['model']->id);

        $jobs->next();
        $this->assertEquals('test.success', $jobs->current()['model']->id);

        $jobs->next();
        $this->assertEquals('test.failed', $jobs->current()['model']->id);
    }
}
