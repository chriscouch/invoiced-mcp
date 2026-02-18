<?php

namespace App\Core\Search\Libs\Strategy;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Interfaces\ReindexingStrategyInterface;
use App\Core\Search\Libs\SearchDocumentFactory;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Model;
use SplFixedArray;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This reindexing strategy syncs a search index
 * with the database in-place, meaning it starts
 * with the current state of the index to minimize
 * operations.
 */
class InPlace implements ReindexingStrategyInterface
{
    const MAX_OBJECTS = 1000000;
    const INSERT_BATCH_SIZE = 1000;
    const IDS_PER_PAGE = 100000;
    private bool $enforceMaxObjectLimit = true;

    public function __construct(private Connection $database, private TenantContext $tenant)
    {
    }

    /**
     * Disables the max object limit for this run.
     */
    public function disableObjectLimit(): void
    {
        $this->enforceMaxObjectLimit = false;
    }

    public function run(Company $company, string $modelClass, IndexInterface $index, bool $dryRun = false, OutputInterface $output = null): void
    {
        $modelName = $modelClass::modelName();

        // See how many records this index should have
        $numObjects = $modelClass::count();

        // Since this is an in-memory operation, there
        // is a limit to how many objects we can sync.
        // This is not an issue on CLI where we can
        // have no memory limit.
        if ($this->enforceMaxObjectLimit && $numObjects > self::MAX_OBJECTS) {
            if ($output) {
                $output->writeln("Skipping sync because there are $numObjects $modelName objects for this account");
            }

            return;
        }

        // Select all object IDs from the DB
        // (stored as a sorted SplFixedArray to conserve memory)
        if ($output) {
            $output->writeln("Fetching $modelName IDs from DB...");
        }
        $dbIds = $this->getDbIds($modelClass);

        // Select all object IDs from the search index
        // (stored as a sorted SplFixedArray to conserve memory)
        if ($output) {
            $output->writeln("Fetching $modelName IDs from search index...");
        }
        $searchIds = $index->getIds();

        // Remove deleted objects from the search index
        $deleted = $this->delete($modelName, $index, $dbIds, $searchIds, $dryRun, $output);

        // Insert missing objects into the search index
        $inserted = $this->insert($modelClass, $modelName, $index, $dbIds, $searchIds, $dryRun, $output);

        if (!$dryRun && $output) {
            $output->writeln('Inserted: '.number_format($inserted).', Deleted: '.number_format($deleted));
        }
    }

    /**
     * Gets the sorted list of IDs from the DB for an object.
     */
    private function getDbIds(string $modelClass): SplFixedArray
    {
        /** @var Model $model */
        $model = new $modelClass();
        $tablename = $model->getTablename();

        $company = $this->tenant->get();

        $result = new SplFixedArray();

        // paginate fetching IDs from the DB
        $i = 0;
        $currCount = false;
        $page = 0;
        $perPage = self::IDS_PER_PAGE;

        while (0 == $page || $currCount == $perPage) {
            $ids = $this->database->createQueryBuilder()
                ->select('id')
                ->from($tablename)
                ->where('tenant_id = :tenantId')
                ->setParameter('tenantId', $company->id())
                ->orderBy('id', 'ASC')
                ->setFirstResult($page * $perPage)
                ->setMaxResults($perPage)
                ->fetchFirstColumn();

            $currCount = count($ids);
            $result->setSize(count($result) + $currCount);
            ++$page;
            foreach ($ids as $id) {
                $result[$i] = (int) $id;
                ++$i;
            }
        }

        return $result;
    }

    /**
     * Deletes the necessary objects from the search index.
     *
     * @return int # of objects deleted
     */
    private function delete(string $modelName, IndexInterface $index, SplFixedArray $dbIds, SplFixedArray $searchIds, bool $dryRun = false, OutputInterface $output = null): int
    {
        if ($output) {
            $output->writeln("Calculating $modelName objects that need to be deleted...");
        }

        // get IDs in the search index but not in the DB
        $deleteIds = $this->getDiff($searchIds, $dbIds);

        $n = count($deleteIds);
        if ($output && $n > 0) {
            $output->writeln("Need to delete $n $modelName objects");
        }

        $deleted = 0;
        foreach ($deleteIds as $id) {
            if (!$dryRun) {
                $index->deleteDocument($id);
                ++$deleted;
            } elseif ($output) {
                $output->writeln("Deleting $modelName # $id...");
            }
        }

        return $deleted;
    }

    /**
     * Inserts the necessary objects into the search index.
     *
     * @return int # of objects inserted
     */
    private function insert(string $modelClass, string $modelName, IndexInterface $index, SplFixedArray $dbIds, SplFixedArray $searchIds, bool $dryRun = false, OutputInterface $output = null): int
    {
        if ($output) {
            $output->writeln("Calculating $modelName objects that need to be inserted...");
        }

        // get IDs in DB but not in the search index
        $insertIds = $this->getDiff($dbIds, $searchIds);

        $n = count($insertIds);
        if ($output && $n > 0) {
            $output->writeln("Need to insert $n $modelName objects");
        }

        $remaining = $n;
        $inserted = 0;
        $start = 0;
        $factory = new SearchDocumentFactory();
        while ($start < $n) {
            $in = $this->sliceAndImplode($insertIds, $start, self::INSERT_BATCH_SIZE, ',');
            $start += self::INSERT_BATCH_SIZE;

            // build IN statement
            $in = 'id IN ('.$in.')';

            // select the model
            $models = $modelClass::where($in)
                ->first(self::INSERT_BATCH_SIZE);

            foreach ($models as $model) {
                if (!$dryRun) {
                    $index->insertDocument($model->id(), $factory->make($model));
                    ++$inserted;
                } elseif ($output) {
                    $output->writeln("Inserting $modelName # {$model->id()}...");
                }
            }

            $remaining -= count($models);
            if ($output && $remaining > 0) {
                $output->writeln("$remaining $modelName objects left to insert");
            }
        }

        return $inserted;
    }

    /**
     * Gets the difference between SPLFixedArray objects.
     *
     * @param SplFixedArray $a sorted list of IDs
     * @param SplFixedArray $b sorted list of IDs
     */
    public function getDiff(SplFixedArray $a, SplFixedArray $b): SplFixedArray
    {
        // Start out with an array with count(A) elements
        // to prevent from needed to resize it. At the end
        // we will shrink down the array to its actual size.
        $diff = new SplFixedArray(count($a));
        $n = 0;

        // Since each array is a list of sorted integers we can
        // walk through A and look for elements not present in B.
        // This method is not as quick as building
        // a hash map, but it is more memory efficient.
        $posB = 0;
        $bLen = count($b);
        $currB = $bLen > 0 ? $b[0] : false;

        foreach ($a as $currA) {
            // Advance the position B until we reach a # that is
            // greater than or equal to the current # from A.
            while ($currA > $currB && $posB + 1 < $bLen) {
                $currB = $b[++$posB];
            }

            // If the current # from A does not match the current #
            // from B then a match does not exist in B.
            // Add it to the difference set.
            if (0 == $bLen || $currA != $currB) {
                $diff[$n] = $currA;
                ++$n;
            }
        }

        // shrink the array down to the actual size
        $diff->setSize($n);

        return $diff;
    }

    /**
     * Array slice + implode for SplFixedArray objects. Used
     * for concatenating a subset of an array.
     */
    public function sliceAndImplode(SplFixedArray $a, int $start, int $count, string $separator): string
    {
        $result = '';

        // ensure we do not exceed the bounds of $a
        $max = min($start + $count, count($a));

        $j = 0;
        for ($i = $start; $i < $max; ++$i) {
            if (0 === $j) {
                $result .= $a[$i];
            } else {
                $result .= $separator.$a[$i];
            }
            ++$j;
        }

        return $result;
    }
}
