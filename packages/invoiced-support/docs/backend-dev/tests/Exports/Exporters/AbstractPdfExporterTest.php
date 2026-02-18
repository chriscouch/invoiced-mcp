<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\Exports\Models\Export;
use App\Tests\AppTestCase;
use Mockery;

abstract class AbstractPdfExporterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    abstract protected function getExporter(ExportStorage $storage): ExporterInterface;

    protected function getExport(): Export
    {
        $export = new Export();
        $export->name = 'Test';
        $export->status = Export::PENDING;
        $export->type = 'test';
        $export->saveOrFail();

        return $export;
    }

    protected function verifyBuild(array $options = []): Export
    {
        $storage = Mockery::mock(ExportStorage::class);
        $result = '';
        $storage->shouldReceive('persist')
            ->andReturnUsing(function (Export $export, string $filename, string $tmpFilename) use (&$result) {
                $result = (string) file_get_contents($tmpFilename);

                return '';
            });

        $export = $this->getExport();
        $exporter = $this->getExporter($storage);
        $exporter->build($export, $options);

        // not much of a test
        $this->assertGreaterThan(0, strlen($result));

        return $export;
    }
}
