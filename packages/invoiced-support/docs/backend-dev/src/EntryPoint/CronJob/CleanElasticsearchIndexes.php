<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Search\Driver\Elasticsearch\ElasticsearchIndexCleaner;

class CleanElasticsearchIndexes implements CronJobInterface
{
    public function __construct(private ElasticsearchIndexCleaner $indexCleaner)
    {
    }

    public static function getName(): string
    {
        return 'delete_unused_elasticsearch_indexes';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function execute(Run $run): void
    {
        $this->indexCleaner->deleteOrphaned();
    }
}
