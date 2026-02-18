<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Tests\Core\RestApi\Book;
use App\Tests\Core\RestApi\Person;
use App\Tests\Core\RestApi\Post;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class CreateModelApiRouteTest extends ModelApiRouteTestBase
{
    protected function getRoute(Request $request): AbstractCreateModelApiRoute
    {
        return new class() extends AbstractCreateModelApiRoute {
            public function getDefinition(): ApiRouteDefinition
            {
                return new ApiRouteDefinition(
                    queryParameters: null,
                    requestParameters: null,
                    requiredPermissions: [],
                );
            }
        };
    }

    public function testGetCreateParameters(): void
    {
        $request = Request::create('/');
        $request->request->replace(['test' => true]);
        $route = $this->getRoute($request);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $this->assertEquals(['test' => true], $context->requestParameters);
    }

    public function testRun(): void
    {
        $model = Mockery::mock(new Person());
        $model->shouldReceive('ids')
            ->andReturn([1]);
        $model->shouldReceive('create')
            ->andReturn(true);
        $route = $this->getRoute(new Request());
        $route->setModel($model);

        $response = self::getService('test.api_runner')->run($route, new Request());

        $this->assertEquals('{"active":false,"address":null,"created_at":null,"email":null,"id":null,"name":null,"updated_at":null}', $response->getContent());
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testRunFail(): void
    {
        $model = Mockery::mock(new Post());
        $model->shouldReceive('create')->andReturn(false);
        $route = $this->getRoute(new Request());
        $route->setModel($model);

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('There was an error creating the Post.');

        self::getService('test.api_runner')->run($route, new Request());
    }

    public function testRunFailWithError(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('error');

        $model = Mockery::mock(new Post());
        $model->shouldReceive('create')->andReturn(false);
        $model->getErrors()->add('error');
        $route = $this->getRoute(new Request());
        $route->setModel($model);

        self::getService('test.api_runner')->run($route, new Request());
    }

    public function testRunMassAssignmentError(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Mass assignment of not_allowed on Book is not allowed');

        $req = Request::create('/', 'POST', ['not_allowed' => true]);
        $route = $this->getRoute($req);
        $route->setModelClass(Book::class);

        self::getService('test.api_runner')->run($route, $req);
    }
}
