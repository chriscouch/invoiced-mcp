<?php

namespace App\Exports\Exporters;

use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use App\Exports\Models\Export;
use App\Metadata\Libs\AttributeHelper;
use Doctrine\DBAL\Connection;

abstract class AbstractExporter implements ExporterInterface
{
    /**
     * @var string[]
     */
    private array $files = [];

    public function __construct(
        private ExportStorage $storage,
        protected Connection $database,
        protected AttributeHelper $attributeHelper,
        protected readonly ListQueryBuilderFactory $listQueryFactory
    ) {
    }

    abstract public static function getId(): string;

    protected function getFileName(Export $export): string
    {
        return $export->name.' '.date($export->tenant()->date_format);
    }

    /**
     * Saves a file to S3.
     */
    protected function persist(Export $export, string $tmpFilename): void
    {
        $filename = $this->getFileName($export);
        $this->files[] = $this->storage->persist($export, $filename, $tmpFilename);
    }

    protected function finish(Export $export): void
    {
        // mark the job as failed or successful
        $export->status = Export::SUCCEEDED;
        $export->download_url = implode(';', $this->files);
        $export->save();
    }

    /**
     * Used for testing.
     */
    public function setStorage(ExportStorage $storage): void
    {
        $this->storage = $storage;
    }
}
