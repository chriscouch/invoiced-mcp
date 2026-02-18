<?php

namespace App\Core\Search\Driver\Database;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\Core\Search\Interfaces\DriverInterface;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Libs\IndexRegistry;
use App\Core\Search\Libs\SearchDocumentFactory;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Model;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Searches the database using MySQL LIKE queries.
 * Since there is no separate index to maintain, the
 * indexing operations do nothing.
 */
class DatabaseDriver implements DriverInterface
{
    private static array $searchableAttributes = [
        Contact::class => [
            'name',
            'email',
            'address1',
            'address2',
            'postal_code',
            'city',
            'state',
            'country',
            'phone',
        ],
        CreditNote::class => [
            'CreditNotes.name',
            'CreditNotes.number',
            'Customers.name',
            'Customers.number',
            'CreditNotes.notes',
        ],
        Customer::class => [
            'name',
            'number',
            'email',
            'address1',
            'address2',
            'postal_code',
            'city',
            'state',
            'country',
            'phone',
            'notes',
        ],
        Invoice::class => [
            'Invoices.name',
            'Invoices.number',
            'Customers.name',
            'Customers.number',
            'Invoices.notes',
        ],
        Estimate::class => [
            'Estimates.name',
            'Estimates.number',
            'Customers.name',
            'Customers.number',
            'Estimates.notes',
        ],
        Payment::class => [
            'Customers.name',
            'Customers.number',
        ],
        Subscription::class => [
            'Customers.name',
            'Customers.number',
            'Plans.name',
            'Plans.id',
        ],
        Vendor::class => [
            'name',
            'number',
        ],
    ];

    private static array $joins = [
        CreditNote::class => [
            [Customer::class, 'customer', 'Customers.id'],
        ],
        Estimate::class => [
            [Customer::class, 'customer', 'Customers.id'],
        ],
        Invoice::class => [
            [Customer::class, 'customer', 'Customers.id'],
        ],
        Payment::class => [
            [Customer::class, 'customer', 'Customers.id'],
        ],
        Subscription::class => [
            [Customer::class, 'customer', 'Customers.id'],
            [Plan::class, 'plan_id', 'Plans.internal_id'],
        ],
    ];

    public function __construct(private IndexRegistry $registry)
    {
    }

    /**
     * @return DatabaseIndex
     */
    public function getIndex(Company $company, string $modelClass): IndexInterface
    {
        return new DatabaseIndex($modelClass);
    }

    public function createIndex(Company $company, string $modelClass, ?string $name = null, bool $dryRun = false, ?OutputInterface $output = null): IndexInterface
    {
        // noop
        return new DatabaseIndex($modelClass);
    }

    public function search(Company $company, string $query, ?string $modelClass, int $numResults): array
    {
        if ($modelClass) {
            $models = [$modelClass];
        } else {
            $models = $this->registry->getIndexableObjectsForCompany($company);
        }

        $numResultsPerIndex = (int) ceil($numResults / count($models));
        $results = [];
        foreach ($models as $model) {
            $results = array_merge($results, $this->searchModel($model, $query, $numResultsPerIndex));
        }

        return array_splice($results, 0, $numResults);
    }

    private function searchModel(string $model, string $query, int $numResults): array
    {
        /** @var Model $model */
        if (!isset(self::$searchableAttributes[$model])) {
            return [];
        }

        $w = [];
        /** @var Connection $database */
        $database = $model::getDriver()->getConnection(null);
        $quotedQuery = $database->quote('%'.$query.'%');
        foreach (self::$searchableAttributes[$model] as $name) {
            $w[] = $database->quoteIdentifier($name)." LIKE $quotedQuery";
        }

        $modelQuery = $model::query();

        // add joins
        if (isset(self::$joins[$model])) {
            foreach (self::$joins[$model] as $join) {
                [$joinModel, $joinColumn, $foreignKey] = $join;
                $modelQuery->join($joinModel, $joinColumn, $foreignKey);
            }
        }

        if (count($w) > 0) {
            $modelQuery->where('('.implode(' OR ', $w).')');
        }

        $results = $modelQuery->first($numResults);
        $factory = new SearchDocumentFactory();

        return array_map(function (Model $model) use ($factory) {
            $result = $factory->make($model);
            $result['id'] = $model->id();
            $result['object'] = $model->object;

            return $result;
        }, $results);
    }
}
