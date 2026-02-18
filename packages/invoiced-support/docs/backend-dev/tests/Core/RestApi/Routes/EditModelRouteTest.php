<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Tests\Core\RestApi\Book;
use App\Tests\Core\RestApi\Person;
use App\Tests\Core\RestApi\Post;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class EditModelRouteTest extends ModelApiRouteTestBase
{
    protected function getRoute(Request $request): AbstractEditModelApiRoute
    {
        return new class() extends AbstractEditModelApiRoute {
            public function getDefinition(): ApiRouteDefinition
            {
                return new ApiRouteDefinition(
                    queryParameters: $this->getBaseQueryParameters(),
                    requestParameters: null,
                    requiredPermissions: [],
                );
            }
        };
    }

    public function testRun(): void
    {
        $model = Mockery::mock(new Person(['id' => 100]));
        $model->refreshWith(['id' => 100, 'name' => 'Bob']);
        $model->shouldReceive('set')
            ->andReturn(true);
        $route = $this->getRoute(new Request());
        $route->setModel($model);

        $response = self::getService('test.api_runner')->run($route, new Request());

        $this->assertEquals('{"active":false,"address":null,"created_at":null,"email":null,"id":100,"name":"Bob","updated_at":null}', $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRunNotFound(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Person was not found: 100');

        $mockModel = Mockery::mock(new Person());
        $mockModel->shouldReceive('find')->andReturn(null);
        $mockModel->shouldReceive('persisted')->andReturn(false);

        $route = $this->getRoute(new Request());
        $route->setModel($mockModel);
        $route->setModelId('100');

        self::getService('test.api_runner')->run($route, new Request());
    }

    public function testRunSetFail(): void
    {
        $post = Mockery::mock(new Post(['id' => 1]));
        $post->shouldReceive('persisted')->andReturn(true);
        $post->shouldReceive('set')->andReturn(false);

        $route = $this->getRoute(new Request([], ['test' => true]));
        $route->setModel($post);

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('There was an error updating the Post.');

        self::getService('test.api_runner')->run($route, new Request());
    }

    public function testBuildResponseValidationError(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('error');

        $model = Mockery::mock(new Person(['id' => 100]));
        $model->refreshWith(['name' => 'Bob']);
        $model->shouldReceive('set')
            ->andReturn(false);
        $route = $this->getRoute(new Request());
        $route->setModel($model);
        $model->getErrors()->add('error');

        self::getService('test.api_runner')->run($route, new Request());
    }

    public function testBuildResponseMassAssignmentError(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('Mass assignment of not_allowed on Book is not allowed');

        $request = Request::create('/', 'POST', ['not_allowed' => true]);
        $route = $this->getRoute($request);
        $model = Mockery::mock(new Book(['id' => 100]));
        $model->refreshWith(['name' => 'Bob']);
        $route->setModel($model);

        self::getService('test.api_runner')->run($route, $request);
    }
}
