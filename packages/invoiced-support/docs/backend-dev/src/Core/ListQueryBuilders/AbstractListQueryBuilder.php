<?php

namespace App\Core\ListQueryBuilders;

use App\AccountsReceivable\Models\Customer;
use App\Automations\Models\AutomationWorkflowEnrollment;
use App\Companies\Models\Company;
use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Filters\FilterFactory;
use App\Core\RestApi\Filters\FilterQuery;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\Database\Exception\QueryException;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ObjectConfiguration;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use App\Reports\ValueObjects\AgingBreakdown;
use Doctrine\DBAL\Connection;

/**
 * @template T of MultitenantModel
 */
abstract class AbstractListQueryBuilder implements ListQueryBuilderInterface
{
    protected Query $query;
    protected Company $company;
    protected array $options;
    /** @var class-string */
    private string $queryClass;

    public function __construct(protected readonly Connection $database)
    {
    }

    public function getBuildQuery(int $limit = 1000): Query
    {
        $this->query->limit($limit);
        $this->applyAutomation($this->query);
        $this->setSort();

        return $this->query;
    }

    public function setSort(string $sort = 'id ASC'): void
    {
        // filter out sort
        if (isset($this->options['sort']) && $this->options['sort']) {
            $sortArray = explode(',', $this->options['sort']);

            $class = $this->getQueryClass();
            $objectType = ObjectType::fromModelClass($class)->typeName();

            $objectConfiguration = ObjectConfiguration::get();
            if ($objectConfiguration->exists($objectType)) {
                $fields = $objectConfiguration->getFields($objectType);
                $sortArray = array_filter($sortArray, function ($sortCandidate) use ($fields) {
                    $sortCandidate = explode(' ', trim($sortCandidate));

                    return str_contains($sortCandidate[0], 'Customers.') || isset($fields[$sortCandidate[0]]);
                });
            }
            if ($sortArray) {
                $sort = implode(',', $sortArray);
            }
        }

        if (str_contains($sort, 'Customers.')) {
            $this->query->join(Customer::class, 'customer', 'Customers.id');
        }
        // NOTE: we add an id sort condition at the end
        // because sorting by fields that have duplicate values
        // (i.e. date) can sometimes produce duplicate rows due
        // to pagination.
        if (!str_contains(strtolower($sort), strtolower('id ASC'))) {
            $sort .= ',id ASC';
        }
        $this->query->sort($sort);
    }

    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        // date column
        $dateColumn = array_value($this->options, 'date_column') ?: 'date';

        // override deprecated start/end date if present
        $start = array_value($this->options, 'start_date');
        if (is_numeric($start) && $start > 0) {
            if (AgingBreakdown::BY_DUE_DATE == $dateColumn && time() < $start) {
                $this->query->where('('.$dateColumn.' IS NULL OR '.$dateColumn.' >= '.$start.')');
            } else {
                $filter = $filter->with(new FilterCondition(FilterOperator::GreaterThanOrEqual, $dateColumn, (int) $start));
            }
        }
        $end = array_value($this->options, 'end_date');
        if (is_numeric($end) && $end > 0) {
            $filter = $filter->with(new FilterCondition(FilterOperator::LessThanOrEqual, $dateColumn, (int) $end));
        }

        return $filter;
    }

    protected function addFilters(): void
    {
        // parse and set filter
        $simpleFilterInput = (array) array_value($this->options, 'filter');
        $advancedFilterInput = array_value($this->options, 'advanced_filter');
        $filter = (new FilterFactory())->makeListFilter($simpleFilterInput, $advancedFilterInput, $this->getQueryClass());
        $filter = $this->fixLegacyOptions($filter);
        FilterQuery::addToQuery($filter, $this->query);
        $this->setMetadataFilter();
    }

    /**
     * @throws QueryException
     */
    protected function fixLegacyNumericJson(ListFilter $filter, string $field): ListFilter
    {
        $totalInput = array_value($this->options, $field);
        if ($totalInput) {
            if ($total = json_decode($totalInput)) {
                if (2 != count($total)) {
                    throw new QueryException('Invalid '.$field.' filter parameter: '.$totalInput);
                }
                $operator = FilterOperator::tryFrom($total[0]);
                if (!$operator) {
                    throw new QueryException('Invalid '.$field.' filter operator: '.$total[0]);
                }

                if (!is_numeric($total[1])) {
                    throw new QueryException('Invalid '.$field.' filter amount: '.$total[1]);
                }

                return $filter->with(new FilterCondition($operator, $field, $total[1]));
            }
        }

        return $filter;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function applyAutomation(Query $query): void
    {
        if ($automation = array_value($this->options, 'automation')) {
            $query->join(AutomationWorkflowEnrollment::class, 'id', 'object_id')
                ->where('AutomationWorkflowEnrollments.workflow_id', $automation);
        }
    }

    /**
     * @param class-string $queryClass
     */
    public function setQueryClass(string $queryClass): void
    {
        $this->queryClass = $queryClass;
    }

    protected function getQueryClass(): string
    {
        return $this->queryClass;
    }

    /**
     * Sets a metadata filter.
     */
    private function setMetadataFilter(): void
    {
        $modelClass = $this->query->getModel();
        $model = new $modelClass();
        if (!$model instanceof MetadataModelInterface) {
            return;
        }

        // parse and set metadata filter
        $input = (array) array_value($this->options, 'metadata');
        $conditions = [];
        foreach ($input as $key => $value) {
            if (is_numeric($key) || !preg_match('/^[A-Za-z0-9_-]*$/', $key)) {
                continue;
            }

            $conditions[] = new MetadataQueryCondition($key, (string) $value, '=');
        }

        if (count($conditions) > 0) {
            $storage = $model->getMetadataReader();
            $companyId = $this->company->id;

            foreach ($storage->buildSqlConditions($conditions, $model, $companyId) as $sql) {
                $this->query->where($sql);
            }
        }
    }
}
