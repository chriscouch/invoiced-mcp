<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Cron\ValueObjects\Run;
use App\EntryPoint\CronJob\GarbageCollection;
use App\Tests\AppTestCase;

class GarbageCollectionTest extends AppTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testRun(): void
    {
        $connection = self::getService('test.database');
        $job = new GarbageCollection($connection);
        $job->execute(new Run());
    }
}
