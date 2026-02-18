<?php

namespace App\Tests\Imports;

use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\InfuseUtility as Utility;
use App\EntryPoint\CronJob\MarkFailedImports;
use App\Imports\Models\Import;
use App\Tests\AppTestCase;

class MarkFailedImportsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testMarkFailed(): void
    {
        $import = new Import();
        $import->name = 'Test';
        $import->type = 'customer';
        $import->status = Import::PENDING;
        $this->assertTrue($import->save());

        $updatedAt = Utility::unixToDb(strtotime('-6 minutes'));
        self::getService('test.database')->update('Imports', ['updated_at' => $updatedAt], ['id' => $import->id()]);

        $job = new MarkFailedImports(self::getService('test.tenant'), self::getService('test.import_job'));
        $job->setStatsd(new StatsdClient());
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());
        $this->assertEquals(Import::FAILED, $import->refresh()->status);
    }
}
