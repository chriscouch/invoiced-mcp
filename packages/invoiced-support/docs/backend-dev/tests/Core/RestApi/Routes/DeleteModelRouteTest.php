<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Tests\Core\RestApi\Person;
use App\Tests\Core\RestApi\Post;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class DeleteModelRouteTest extends ModelApiRouteTestBase
{
    protected function getRoute(Request $request): AbstractDeleteModelApiRoute
    {
        return new class() extends AbstractDeleteModelApiRoute {
            public function getDefinition(): ApiRouteDefinition
            {
                return new ApiRouteDefinition(
                    queryParameters: [],
                    requestParameters: null,
                    requiredPermissions: [],
                );
            }
        };
    }

    public function testRun(): void
    {
        $model = Mockery::mock(new Person(['id' => 100]));
        $model->refreshWith(['name' => 'Bob']);
        $model->shouldReceive('delete')
            ->andReturn(true);
        $route = $this->getRoute(new Request());
        $route->setModel($model);

        $response = self::getService('test.api_runner')->run($route, new Request());

        $this->assertEquals('', $response->getContent());
        $this->assertEquals(204, $response->getStatusCode());
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

    public function testRunDeleteFail(): void
    {
        $post = Mockery::mock(new Post(['id' => 1]));
        $post->shouldReceive('persisted')->andReturn(true);
        $post->shouldReceive('delete')->andReturn(false);

        $route = $this->getRoute(new Request());
        $route->setModel($post);

        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('There was an error deleting the Post.');

        self::getService('test.api_runner')->run($route, new Request());
    }

    public function testRunValidationError(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessage('error');

        $model = Mockery::mock(new Person(['id' => 10]));
        $model->shouldReceive('delete')
            ->andReturn(false);
        $model->refreshWith(['name' => 'test']);
        $route = $this->getRoute(new Request());
        $route->setModel($model);
        $model->getErrors()->add('error');

        self::getService('test.api_runner')->run($route, new Request());
    }
}
