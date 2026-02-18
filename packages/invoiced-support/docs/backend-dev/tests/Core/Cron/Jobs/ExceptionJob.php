<?php

namespace App\Tests\Core\Cron\Jobs;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;

class ExceptionJob implements CronJobInterface
{
    public static function getName(): string
    {
        return 'exception';
    }

    public static function getLockTtl(): int
    {
        return 30;
    }

    public function execute(Run $run): void
    {
        throw new \Exception('test');
    }
}
