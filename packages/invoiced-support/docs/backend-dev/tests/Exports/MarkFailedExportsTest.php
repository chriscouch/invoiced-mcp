<?php

namespace App\Tests\Exports;

use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\InfuseUtility as Utility;
use App\EntryPoint\CronJob\MarkFailedExports;
use App\Exports\Models\Export;
use App\Tests\AppTestCase;

class MarkFailedExportsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testMarkFailed(): void
    {
        $export = new Export();
        $export->name = 'Test';
        $export->type = 'customer';
        $export->status = Export::PENDING;
        $this->assertTrue($export->save());

        $createdAt = Utility::unixToDb(strtotime('-11 minutes'));
        self::getService('test.database')->update('Exports', ['created_at' => $createdAt], ['id' => $export->id()]);

        $job = new MarkFailedExports(self::getService('test.tenant'), self::getService('test.mailer'));
        $job->setStatsd(new StatsdClient());
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());
        $this->assertEquals(Export::FAILED, $export->refresh()->status);
    }
}
