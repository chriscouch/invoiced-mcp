<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\Events\CronJobBeginEvent;
use App\Core\Cron\FileGetContentsMock;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\Libs\Runner;
use App\Core\Cron\Models\CronJob;
use App\Core\Cron\ValueObjects\Run;
use App\Tests\AppTestCase;
use App\Tests\Core\Cron\Jobs\ExceptionJob;
use App\Tests\Core\Cron\Jobs\FailJob;
use App\Tests\Core\Cron\Jobs\SuccessJob;
use App\Tests\Core\Cron\Jobs\TestJob;
use Mockery;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RunnerTest extends AppTestCase
{
    private static EventDispatcher $dispatcher;

    public static function setUpBeforeClass(): void
    {
        include_once 'file_get_contents_mock.php';

        self::getService('test.database')->executeStatement('DELETE FROM CronJobs WHERE id LIKE "test%"');

        self::$dispatcher = new EventDispatcher();
    }

    protected function setUp(): void
    {
        FileGetContentsMock::$functions = Mockery::mock();
    }

    private function getRunner(CronJob $jobModel, ?CronJobInterface $job = null): Runner
    {
        $job = $job ?? new TestJob();

        return new Runner($jobModel, $job, self::$dispatcher);
    }

    public function testGetJobModel(): void
    {
        $job = new CronJob();
        $runner = $this->getRunner($job);
        $this->assertEquals($job, $runner->getJobModel());
    }

    public function testGetJob(): void
    {
        $job = new CronJob();
        $runner = $this->getRunner($job);
        $this->assertInstanceOf(TestJob::class, $runner->getJob());
    }

    public function testGoException(): void
    {
        $jobModel = new CronJob();
        $jobModel->id = 'test.exception';
        $job = new ExceptionJob();
        $runner = $this->getRunner($jobModel, $job);

        $run = $runner->go();
        $this->assertInstanceOf(Run::class, $run);
        $this->assertEquals(Run::RESULT_FAILED, $run->getResult());

        $this->assertTrue($jobModel->persisted());
        $this->assertGreaterThan(0, $jobModel->last_ran);
        $this->assertFalse($jobModel->last_run_succeeded);
        $this->assertEquals('test', $jobModel->last_run_output);
    }

    public function testGoFailed(): void
    {
        $jobModel = new CronJob();
        $jobModel->id = 'test.fail';
        $job = new FailJob();
        $runner = $this->getRunner($jobModel, $job);

        $run = $runner->go();
        $this->assertInstanceOf(Run::class, $run);
        $this->assertEquals(Run::RESULT_FAILED, $run->getResult());

        $this->assertTrue($jobModel->persisted());
        $this->assertGreaterThan(0, $jobModel->last_ran);
        $this->assertFalse($jobModel->last_run_succeeded);
    }

    public function testGoRejectedBeginEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CronJobBeginEvent::NAME, function (CronJobBeginEvent $event) {
            $event->stopPropagation();
        });

        $jobModel = new CronJob();
        $jobModel->id = 'test.reject';
        $job = new SuccessJob();
        $runner = new Runner($jobModel, $job, $dispatcher);

        $run = $runner->go();
        $this->assertInstanceOf(Run::class, $run);
        $this->assertEquals(Run::RESULT_FAILED, $run->getResult());

        $this->assertTrue($jobModel->persisted());
        $this->assertGreaterThan(0, $jobModel->last_ran);
        $this->assertFalse($jobModel->last_run_succeeded);
        $this->assertEquals('Rejected by cron_job.begin event listener', $jobModel->last_run_output);
    }

    public function testGoSuccess(): void
    {
        $jobModel = new CronJob();
        $jobModel->id = 'test.success';
        $job = new SuccessJob();
        $runner = $this->getRunner($jobModel, $job);

        $run = $runner->go();
        $this->assertInstanceOf(Run::class, $run);
        $this->assertEquals(Run::RESULT_SUCCEEDED, $run->getResult());

        $this->assertTrue($jobModel->persisted());
        $this->assertGreaterThan(0, $jobModel->last_ran);
        $this->assertTrue($jobModel->last_run_succeeded);
        $this->assertEquals("test run obj\ntest", $jobModel->last_run_output);
    }

    public function testGoSuccessNoReturnValue(): void
    {
        $jobModel = new CronJob();
        $jobModel->id = 'test.invoke';
        $runner = $this->getRunner($jobModel);

        $run = $runner->go();
        $this->assertInstanceOf(Run::class, $run);
        $this->assertEquals(Run::RESULT_SUCCEEDED, $run->getResult());

        $this->assertTrue($jobModel->persisted());
        $this->assertGreaterThan(0, $jobModel->last_ran);
        $this->assertTrue($jobModel->last_run_succeeded);
        $this->assertEquals('works', $jobModel->last_run_output);
    }
}
