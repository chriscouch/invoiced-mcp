<?php

namespace App\Core\Search\Libs\Strategy;

use App\Companies\Models\Company;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Interfaces\ReindexingStrategyInterface;
use App\Core\Search\Libs\Search;
use App\Core\Search\Libs\SearchDocumentFactory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This reindexing strategy rebuilds a search index
 * from scratch and overwrites the old index.
 */
class Rebuild implements ReindexingStrategyInterface
{
    public function __construct(private Search $search, private ?int $indexLimit = null)
    {
    }

    public function run(Company $company, string $modelClass, IndexInterface $index, bool $dryRun = false, OutputInterface $output = null): void
    {
        // Create a temp index
        $tempName = $index->getName().'_temp';
        $driver = $this->search->getDriver($company);
        $tempIndex = $driver->createIndex($company, $modelClass, $tempName, $dryRun, $output);

        if ($output) {
            $output->writeln("Creating index $tempName...");
        }

        // Iterate over each object and add it to the temp index
        if ($output) {
            $output->writeln('Rebuilding index...');
        }

        // Some environments (i.e. sandbox) have a limit
        // on the # of objects that an index can contain
        // in order to keep the costs down.
        $modelName = $modelClass::modelName();
        $inserted = 0;
        $factory = new SearchDocumentFactory();
        foreach ($modelClass::all() as $object) {
            if ($this->indexLimit > 0 && $inserted > $this->indexLimit) {
                break;
            }

            $id = $object->id();
            $tempIndex->insertDocument($id, $factory->make($object));
            ++$inserted;

            if ($dryRun && $output) {
                $output->writeln("Inserting $modelName # $id");
            }
        }

        if (!$dryRun && $output) {
            $output->writeln("Inserted $inserted $modelName objects");
        }

        // Move the temp index to replace the existing one
        if ($output) {
            $output->writeln("Moving the temp index to {$index->getName()}...");
        }

        $tempIndex->rename($index->getName());
    }
}
