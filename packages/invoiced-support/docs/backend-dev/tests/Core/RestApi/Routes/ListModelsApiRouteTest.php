<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\Orm\Query;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\SimpleCache;
use App\Tests\Core\RestApi\Person;
use Mockery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ListModelsApiRouteTest extends ModelApiRouteTestBase
{
    protected function getRoute(Request $request): AbstractListModelsApiRoute
    {
        $apiCache = new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter()));

        return new class($apiCache) extends AbstractListModelsApiRoute {
            public function getDefinition(): ApiRouteDefinition
            {
                return new ApiRouteDefinition(
                    queryParameters: null,
                    requestParameters: null,
                    requiredPermissions: [],
                    filterableProperties: ['active'],
                );
            }
        };
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testNoPermission(): void
    {
    }

    public function testGetPage(): void
    {
        $route = $this->getRoute(new Request());

        $this->assertEquals(1, $route->getPage());

        $req = new Request(['page' => 50]);
        $route = $this->getRoute($req);
        self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $this->assertEquals(50, $route->getPage());
    }

    public function testGetPerPage(): void
    {
        $route = $this->getRoute(new Request());

        $this->assertEquals(100, $route->getPerPage());

        $req = new Request(['per_page' => 50]);
        $route = $this->getRoute($req);
        self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $this->assertEquals(50, $route->getPerPage());
    }

    public function testGetPerPageLimit(): void
    {
        $req = new Request(['per_page' => 1000]);
        $route = $this->getRoute($req);
        self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $this->assertEquals(100, $route->getPerPage());
    }

    public function testFilter(): void
    {
        $route = $this->getRoute(new Request());

        $this->assertEquals([], $route->getFilter());

        $filter = ['test' => 'blah', 'invalid' => [], 'invalid2"*)#$*#)%' => []];
        $req = new Request(['filter' => $filter]);
        $route = $this->getRoute($req);
        self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $this->assertEquals($filter, $route->getFilter());
    }

    public function testExpand(): void
    {
        $route = $this->getRoute(new Request());

        $this->assertEquals([], $route->getExpand());

        $req = new Request(['expand' => 'test,blah']);
        $route = $this->getRoute($req);
        self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $this->assertEquals(['test', 'blah'], $route->getExpand());
    }

    public function testJoin(): void
    {
        $route = $this->getRoute(new Request());
        $context = self::getService('test.api_runner')->validateRequest(new Request(), $route->getDefinition());
        $route->parseListParameters($context->request);

        $this->assertEquals([], $route->getJoin());
    }

    public function testSort(): void
    {
        $route = $this->getRoute(new Request());
        $context = self::getService('test.api_runner')->validateRequest(new Request(), $route->getDefinition());
        $route->parseListParameters($context->request);

        $this->assertNull($route->getSort());

        $req = new Request(['sort' => 'name desc']);
        $route = $this->getRoute($req);
        self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $this->assertEquals('name desc', $route->getSort());
    }

    public function testGetEndpoint(): void
    {
        $request = Request::create('https://example.com/api/v1/users/', 'post');

        // try without an API URL or base path defined
        $route = $this->getRoute($request);
        self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);

        $this->assertEquals('https://example.com/api/v1/users', $route->getEndpoint($request));

        // Try with an API URL
        $route = $this->getRoute($request);
        $route->setApiUrlBase('https://api.example.com/');
        self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);

        $this->assertEquals('https://api.example.com/api/v1/users', $route->getEndpoint($request));
    }

    public function testBuildQuery(): void
    {
        $request = new Request([
            'page' => 3,
            'per_page' => 50,
            'filter' => ['active' => true],
            'expand' => 'address,address_shim',
            'sort' => 'name ASC',
            'paginate' => 'none',
            'advanced_filter' => null,
        ]);
        $route = $this->getRoute($request);
        $route->setModelClass(Person::class);
        $route->setJoin([['Address', 'id', 'address_id']]);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);

        $query = $route->buildQuery($context);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals(['address', 'address_shim'], $query->getWith());
        $this->assertEquals([['Address', 'id', 'address_id', 'JOIN']], $query->getJoins());
        $this->assertEquals([['active', true, '=']], $query->getWhere());
        $this->assertEquals([['name', 'asc']], $query->getSort());

        $route->applyPaginationToQuery($query);
        $this->assertEquals(100, $query->getStart());
        $this->assertEquals(50, $query->getLimit());
    }

    public function testBuildQueryInvalidFilterPropertyString(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid filter parameter: *#)*$J)F(');

        $request = new Request(['filter' => ['*#)*$J)F(' => true]]);
        $route = $this->getRoute($request);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);
        $route->setModelClass(Person::class);

        $route->buildQuery($context);
    }

    public function testBuildQueryInvalidFilterProperty(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid filter parameter: test');

        $request = new Request(['filter' => ['test' => true]]);
        $route = $this->getRoute($request);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);
        $route->setModelClass(Person::class);

        $route->buildQuery($context);
    }

    public function testBuildQueryInvalidFilterValue(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Invalid value for `active` filter parameter');

        $request = new Request(['filter' => ['active' => ['test' => true]]]);
        $route = $this->getRoute($request);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->parseListParameters($request);
        $route->setModelClass(Person::class);

        $route->buildQuery($context);
    }

    public function testPaginateDefault(): void
    {
        $req = Request::create('https://example.com/api/models', 'GET', ['sort' => 'name ASC', 'per_page' => 100]);
        $route = $this->getRoute($req);
        $context = self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $response = new Response();
        $route->paginate($context, $response, 2, 50, null, [], 200);

        // Default pagination mode is offset
        $this->assertEquals(200, $response->headers->get('X-Total-Count'));
        $this->assertEquals('<https://example.com/api/models?sort=name+ASC&per_page=50&page=2>; rel="self", <https://example.com/api/models?sort=name+ASC&per_page=50&page=1>; rel="first", <https://example.com/api/models?sort=name+ASC&per_page=50&page=1>; rel="previous", <https://example.com/api/models?sort=name+ASC&per_page=50&page=3>; rel="next", <https://example.com/api/models?sort=name+ASC&per_page=50&page=4>; rel="last"', $response->headers->get('Link'));
    }

    public function testPaginateOffset(): void
    {
        $req = Request::create('https://example.com/api/models', 'GET', ['sort' => 'name ASC', 'per_page' => 100, 'paginate' => 'offset']);
        $route = $this->getRoute($req);
        $context = self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $response = new Response();
        $route->paginate($context, $response, 2, 50, null, [], 200);

        $this->assertEquals(200, $response->headers->get('X-Total-Count'));
        $this->assertEquals('<https://example.com/api/models?sort=name+ASC&paginate=offset&per_page=50&page=2>; rel="self", <https://example.com/api/models?sort=name+ASC&paginate=offset&per_page=50&page=1>; rel="first", <https://example.com/api/models?sort=name+ASC&paginate=offset&per_page=50&page=1>; rel="previous", <https://example.com/api/models?sort=name+ASC&paginate=offset&per_page=50&page=3>; rel="next", <https://example.com/api/models?sort=name+ASC&paginate=offset&per_page=50&page=4>; rel="last"', $response->headers->get('Link'));
    }

    public function testPaginateNone(): void
    {
        $req = Request::create('https://example.com/api/models', 'GET', ['sort' => 'name ASC', 'per_page' => 100, 'paginate' => 'none']);
        $route = $this->getRoute($req);
        $context = self::getService('test.api_runner')->validateRequest($req, $route->getDefinition());
        $route->parseListParameters($req);

        $response = new Response();
        $route->paginate($context, $response, 2, 50, null, [], 200);

        $this->assertFalse($response->headers->has('X-Total-Count'));
        $this->assertFalse($response->headers->has('Link'));
    }

    public function testRun(): void
    {
        $model1 = new Person();
        $model1->refreshWith(['id' => 1]);
        $model2 = new Person();
        $model2->refreshWith(['id' => 2]);

        $query = Mockery::mock(Query::class);
        $query->shouldReceive('getModel')->andReturn(Person::class);
        $query->shouldReceive('where');
        $query->shouldReceive('getJoins')->andReturn([]);
        $query->shouldReceive('getWhere')->andReturn([]);
        $query->shouldReceive('start');
        $query->shouldReceive('limit');
        $query->shouldReceive('getSort')->andReturn([['first_name', 'asc'], ['last_name', 'asc']]);
        $query->shouldReceive('execute')->andReturn([$model1, $model2]);
        $query->shouldReceive('count')->andReturn(2);

        $mockModel = Mockery::mock('alias:PersonMock');
        $mockModel->shouldReceive('query')->andReturn($query); /* @phpstan-ignore-line */

        $route = $this->getRoute(new Request());
        $route->setModelClass('PersonMock'); /* @phpstan-ignore-line */

        $response = self::getService('test.api_runner')->run($route, new Request());

        $this->assertEquals('[{"active":false,"address":null,"created_at":null,"email":null,"id":1,"name":null,"updated_at":null},{"active":false,"address":null,"created_at":null,"email":null,"id":2,"name":null,"updated_at":null}]', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());

        // verify headers
        $this->assertGreaterThan(0, strlen((string) $response->headers->get('Link')));
        $this->assertEquals(2, $response->headers->get('X-Total-Count'));
    }
}
