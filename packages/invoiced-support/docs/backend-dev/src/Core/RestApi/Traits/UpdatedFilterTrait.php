<?php

namespace App\Core\RestApi\Traits;

use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;
use App\Core\Utils\InfuseUtility as Utility;
use Symfony\Component\HttpFoundation\Request;

/**
 * This trait adds `updated_before` and `updated_after`
 * filters to list API endpoints.
 */
trait UpdatedFilterTrait
{
    private ?int $updatedBefore = null;
    private ?int $updatedAfter = null;

    /**
     * Parses the request for updated timestamp filters.
     */
    public function parseRequestUpdated(Request $request): void
    {
        // updated before
        $updatedBefore = (int) $request->query->get('updated_before');
        if ($updatedBefore > 0) {
            $this->setUpdatedBefore($updatedBefore);
        }

        // updated after
        $updatedAfter = (int) $request->query->get('updated_after');
        if ($updatedAfter > 0) {
            $this->setUpdatedAfter($updatedAfter);
        }
    }

    protected function getUpdatedQueryParameters(): array
    {
        return [
            'updated_before' => new QueryParameter(
                types: ['int', 'string'],
                default: 0,
            ),
            'updated_after' => new QueryParameter(
                types: ['int', 'string'],
                default: 0,
            ),
        ];
    }

    /**
     * Sets the updated before timestamp.
     */
    public function setUpdatedBefore(int $t): void
    {
        $this->updatedBefore = $t;
    }

    /**
     * Sets the updated after timestamp.
     */
    public function setUpdatedAfter(int $t): void
    {
        $this->updatedAfter = $t;
    }

    /**
     * Gets the updated before timestamp.
     */
    public function getUpdatedBefore(): ?int
    {
        return $this->updatedBefore;
    }

    /**
     * Gets the updated after timestamp.
     */
    public function getUpdatedAfter(): ?int
    {
        return $this->updatedAfter;
    }

    /**
     * Adds any updated timestamp filters to the query.
     */
    public function addUpdatedFilterToQuery(Query $query, string $tablename = ''): Query
    {
        $k = $tablename ? "$tablename.updated_at" : 'updated_at';
        if ($t = $this->updatedBefore) {
            $query->where($k, Utility::unixToDb($t), '<');
        }

        if ($t = $this->updatedAfter) {
            $query->where($k, Utility::unixToDb($t), '>');
        }

        return $query;
    }
}
