<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Filters\FilterFactory;
use App\Core\RestApi\Filters\FilterQuery;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template T
 *
 * @extends AbstractModelApiRoute<T>
 */
abstract class AbstractListModelsApiRoute extends AbstractModelApiRoute
{
    const DEFAULT_PER_PAGE = 100;
    const PAGE_LIMIT = 100;

    protected int $perPage = self::DEFAULT_PER_PAGE;
    protected int $page = 1;
    protected array $filter = [];
    protected array $expand = [];
    protected array $join = [];
    protected ?string $sort = null;
    protected Response $response;
    private string $apiUrlBase = '';
    private ?string $cursorPaginationColumn = null;
    private ?Query $paginationOriginalQuery = null;

    public function __construct(
        private readonly ApiCache $apiCache
    ) {
    }

    protected function getBaseQueryParameters(): array
    {
        $parameters = parent::getBaseQueryParameters();

        $parameters['per_page'] = new QueryParameter(
            types: ['int', 'string'],
            default: self::DEFAULT_PER_PAGE,
        );
        $parameters['page'] = new QueryParameter(
            types: ['int', 'string'],
            default: 1,
        );
        $parameters['sort'] = new QueryParameter(
            types: ['string'],
            default: '',
        );
        $parameters['paginate'] = new QueryParameter(
            allowedValues: ['offset', 'none'],
            default: 'offset',
        );
        $parameters['filter'] = new QueryParameter(
            types: ['array'],
            default: [],
        );
        $parameters['advanced_filter'] = new QueryParameter(
            types: ['string'],
        );

        return $parameters;
    }

    public function setApiUrlBase(string $apiUrlBase): void
    {
        $this->apiUrlBase = $apiUrlBase;
    }

    /**
     * Gets the maximum # of results to return.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Gets the page #.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Gets the expanded properties.
     */
    public function getExpand(): array
    {
        return $this->expand;
    }

    /**
     * Gets the query filter.
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    /**
     * Gets the join conditions.
     */
    public function getJoin(): array
    {
        return $this->join;
    }

    /**
     * Sets the join conditions.
     */
    public function setJoin(array $join): void
    {
        $this->join = $join;
    }

    /**
     * Gets the sort string.
     */
    public function getSort(): ?string
    {
        return $this->sort;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->parseListParameters($context->request);

        if (!$this->model) {
            throw $this->requestNotRecognizedError($context->request);
        }

        $query = $this->buildQuery($context);
        $this->applyPaginationToQuery($query);
        $models = $query->execute();

        $this->response = new Response();
        $this->paginate($context, $this->response, $this->page, $this->perPage, $query, $models);

        return $models;
    }

    public function parseListParameters(Request $request): void
    {
        // parse pagination
        if ($request->query->get('per_page')) {
            $this->perPage = (int) $request->query->get('per_page');
        }

        $this->perPage = max(1, min(static::PAGE_LIMIT, $this->perPage));

        if ($page = $request->query->get('page')) {
            $this->page = (int) $page;
        }

        $this->page = max(1, $this->page);

        // parse filter parameters
        $this->filter = $request->query->all('filter');

        // parse expansions
        $expand = $request->query->get('expand');
        if (is_string($expand)) {
            $this->expand = explode(',', $expand);
        }

        // parse sort parameters
        $this->sort = $request->query->get('sort');
    }

    public function getSuccessfulResponse(): Response
    {
        return $this->response;
    }

    /**
     * Builds the model query.
     *
     * @return Query<T>
     */
    public function buildQuery(ApiCallContext $context): Query
    {
        /** @var Model|null $model */
        $model = $this->model;
        if (!$model) {
            throw new InvalidRequest('Model not specified');
        }

        $query = $model::query();

        // perform joins
        foreach ($this->join as $condition) {
            [$joinModel, $column, $foreignKey] = $condition;
            $query->join($joinModel, $column, $foreignKey);
        }

        // parse the filter and add to the ORM query
        $filter = $this->parseFilterInput($context, $this->filter);
        FilterQuery::addToQuery($filter, $query);

        // eager loading
        foreach ($this->expand as $k) {
            $property = $model::definition()->get($k);
            if ($property && $property->relation_type) {
                $query->with($k);
            }
        }

        if (isset($this->sort)) {
            $query->sort($this->sort);
        }

        return $query;
    }

    /**
     * @throws InvalidRequest
     */
    public function applyPaginationToQuery(Query $query): void
    {
        $query->limit($this->perPage);

        // Determine if we can use cursor pagination on this API call.
        // Cursor pagination offers a performance benefit when iterating through
        // a large set of records. With offset pagination the performance degrades
        // as the page number increments. Cursor pagination performs better on
        // a larger data set. Cursor pagination is only used when sorting solely on
        // an auto-increment primary key.

        // When cursor pagination is not available then fall back to offset pagination
        $offset = ($this->page - 1) * $this->perPage;
        if (!$this->applyPaginationCursor($query, $offset)) {
            $this->applyPaginationOffset($query, $offset);
        }
    }

    /**
     * @throws InvalidRequest
     */
    private function applyPaginationCursor(Query $query, int $offset): bool
    {
        // When no sort condition is specified then sort based on PK ascending
        if (0 == count($query->getSort())) {
            $model = $this->getModel();
            $ids = $model::definition()->getIds();
            if (1 == count($ids)) {
                $query->sort($ids[0].' asc');
            }
        }

        $sort = $query->getSort();
        if (1 != count($sort)) {
            return false;
        }

        $model = $this->getModel();
        $ids = $model::definition()->getIds();
        if (1 != count($ids) || $sort[0][0] != $ids[0]) {
            return false;
        }

        $this->cursorPaginationColumn = $ids[0];

        if ($offset <= 0) {
            return false;
        }

        $cursor = $this->apiCache->getPaginationCursor($query, $offset);
        // Cursor pagination is required with natural sorting
        // on higher offsets. This kicks in at offset 10,000.
        if (!$cursor && $offset >= 10000) {
            throw new InvalidRequest('You must retrieve all preceding page numbers before retrieving this page.');
        }

        if (!$cursor) {
            return false;
        }

        // Clone the query before modifying it to add pagination SQL
        $this->paginationOriginalQuery = clone $query;

        // Since the natural sort is always based on the PK ascending
        // then we want to use > as the operator.
        $operator = 'asc' == $sort[0][1] ? '>' : '<';
        $query->where($this->cursorPaginationColumn, (int) $cursor, $operator);

        return true;
    }

    private function applyPaginationOffset(Query $query, int $offset): void
    {
        $query->start($offset);
    }

    /**
     * Paginates the results from this route.
     */
    public function paginate(ApiCallContext $context, Response $response, int $page, int $perPage, ?Query $query, array $models, ?int $total = null): void
    {
        $mode = $context->queryParameters['paginate'] ?? 'offset'; // not every list api route uses the query options resolver
        if ('offset' == $mode) {
            $this->paginateOffset($context, $response, $page, $perPage, $query, $models, $total);
        }
    }

    private function paginateOffset(ApiCallContext $context, Response $response, int $page, int $perPage, ?Query $query, array $models, ?int $total): void
    {
        $originalQuery = $this->paginationOriginalQuery ?? $query;
        if (null === $total) {
            // The first page always computes the
            // total count instead of using a cached value.
            $recompute = 1 == $this->page;
            $total = $originalQuery ? $this->apiCache->getCachedQueryCount($originalQuery, $recompute) : 0;
        }

        // set X-Total-Count header
        $response->headers->set('X-Total-Count', (string) $total);

        // compute links
        $pageCount = max(1, ceil($total / $perPage));
        $base = $this->getEndpoint($context->request);

        $requestQuery = $context->queryParameters;

        // remove any previously set per_page value
        if (isset($requestQuery['per_page'])) {
            unset($requestQuery['per_page']);
        }

        // set the per_page value unless it's the default
        if (self::DEFAULT_PER_PAGE != $perPage) {
            $requestQuery['per_page'] = $perPage;
        }

        // self/first links
        $links = [
            'self' => $this->link($base, array_replace($requestQuery, ['page' => $page])),
            'first' => $this->link($base, array_replace($requestQuery, ['page' => 1])),
        ];

        // previous/next links
        if ($page > 1) {
            $links['previous'] = $this->link($base, array_replace($requestQuery, ['page' => $page - 1]));
        }

        if ($page < $pageCount) {
            $links['next'] = $this->link($base, array_replace($requestQuery, ['page' => $page + 1]));
        }

        // last link
        $links['last'] = $this->link($base, array_replace($requestQuery, ['page' => $pageCount]));

        // build Link header
        $linkStr = implode(', ', array_map(fn ($link, $rel) => "<$link>; rel=\"$rel\"", $links, array_keys($links)));

        $response->headers->set('Link', $linkStr);

        // Store the last result as the cursor in order for the next
        // page to use cursor pagination instead of offset pagination.
        if ($this->cursorPaginationColumn && $originalQuery && count($models) > 0) {
            $offset = $page * $perPage;
            $cursor = (string) $models[count($models) - 1]->{$this->cursorPaginationColumn};
            $this->apiCache->storePaginationCursor($originalQuery, $offset, $cursor);
        }
    }

    /**
     * Gets the full URL for this API route.
     */
    public function getEndpoint(Request $request): string
    {
        // determine the base URL for the API,
        // i.e. https://api.example.com/v1
        if ($this->apiUrlBase) {
            $urlBase = $this->stripTrailingSlash($this->apiUrlBase);
        } else {
            $urlBase = $request->getSchemeAndHttpHost().$request->getBasePath();
        }

        // get the requested path, strip any trailing '/'
        $path = $this->stripTrailingSlash($request->getPathInfo());

        return $urlBase.$path;
    }

    /**
     * Strips any trailing '/' from a string.
     */
    private function stripTrailingSlash(string $str): string
    {
        while (str_ends_with($str, '/')) {
            $str = substr($str, 0, -1);
        }

        return $str;
    }

    /**
     * Builds the filter from an input array of parameters.
     *
     * @throws InvalidRequest when an invalid input parameter was used
     */
    protected function parseFilterInput(ApiCallContext $context, array $input): ListFilter
    {
        $model = $this->model;
        if (!$model) {
            throw new InvalidRequest('Model not specified');
        }

        $factory = new FilterFactory();
        $modelClass = get_class($model);
        $allowed = array_merge(
            $context->definition->filterableProperties,
            $factory->getFilterableProperties($modelClass)
        );

        // Parse the advanced filter input if given.
        return (new FilterFactory())->makeListFilter($input, $context->queryParameters['advanced_filter'] ?? null, $modelClass, $allowed);
    }

    /**
     * Generates a pagination link.
     */
    private function link(string $url, array $query): string
    {
        return $url.((count($query) > 0) ? '?'.http_build_query($query) : '');
    }
}
