<?php

namespace App\Tests\Core\Cron\Jobs;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;

class SuccessSkipJob implements CronJobInterface
{
    public static function getName(): string
    {
        return 'test.success.skip';
    }

    public static function getLockTtl(): int
    {
        return 30;
    }

    public function execute(Run $run): void
    {
        $run->writeOutput('test run obj');
        $run->writeOutput('test');
    }
}
