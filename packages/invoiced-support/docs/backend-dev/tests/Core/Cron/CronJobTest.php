<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\Models\CronJob;
use App\Tests\AppTestCase;

class CronJobTest extends AppTestCase
{
    private static CronJob $job;

    public static function setUpBeforeClass(): void
    {
        self::getService('test.database')->executeStatement('DELETE FROM CronJobs WHERE id LIKE "test%"');
    }

    public function testCreate(): void
    {
        self::$job = new CronJob();
        self::$job->id = 'test.test';
        $this->assertTrue(self::$job->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$job->last_ran = time();
        $this->assertTrue(self::$job->save());
    }
}
