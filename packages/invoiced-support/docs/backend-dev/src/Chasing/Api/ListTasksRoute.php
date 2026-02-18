<?php

namespace App\Chasing\Api;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Note;
use App\Chasing\Models\Task;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Reports\Libs\AgingReport;
use App\Reports\ValueObjects\AgingBreakdown;
use Doctrine\DBAL\Connection;

/**
 * @extends AbstractListModelsApiRoute<Task>
 */
class ListTasksRoute extends AbstractListModelsApiRoute
{
    private array $notes = [];

    public function __construct(
        private TenantContext $tenant,
        private Connection $database,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Task::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        // join customers for sorting
        if (str_contains((string) $this->sort, 'Customers.')) {
            $query->join(Customer::class, 'customer_id', 'id');
        }

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Task[] $tasks */
        $tasks = parent::buildResponse($context);

        // Build response with recent note and aging
        if ($this->isParameterIncluded($context, 'aging')) {
            $this->withAging($tasks);
        }

        if ($this->isParameterIncluded($context, 'most_recent_note')) {
            $this->withNotes($tasks);
        }

        return $tasks;
    }

    private function withAging(array $tasks): void
    {
        $customerIds = [];
        foreach ($tasks as $task) {
            if ($task->customer_id) {
                $customerIds[] = $task->customer_id;
            }
        }
        if (!$customerIds) {
            return;
        }

        $company = $this->tenant->get();
        $agingBreakdown = AgingBreakdown::fromSettings($company->accounts_receivable_settings);
        $aging = new AgingReport($agingBreakdown, $company, $this->database);

        $customerAging = $aging->buildForCustomers($customerIds);

        // Build response with recent note and aging
        foreach ($tasks as $task) {
            $task->setMostRecentNote($this->getMostRecentNote($task->customer_id));
            $rows = $customerAging[$task->customer_id] ?? [];
            foreach ($rows as &$row) {
                $row['amount'] = $row['amount']->toDecimal();
            }
            $task->setAging($rows);
        }
    }

    private function withNotes(array $tasks): void
    {
        foreach ($tasks as $task) {
            $task->setMostRecentNote($this->getMostRecentNote($task->customer_id));
        }
    }

    private function getMostRecentNote(?int $customerId): ?string
    {
        if (!$customerId) {
            return null;
        }
        if (!isset($this->notes[$customerId])) {
            $mostRecentNote = Note::where('customer_id', $customerId)
                ->sort('id DESC')
                ->oneOrNull();
            $this->notes[$customerId] = $mostRecentNote instanceof Note ? $mostRecentNote->notes : null;
        }

        return $this->notes[$customerId];
    }
}
