<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Tests\Core\RestApi\Person;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class RetrieveModelRouteTest extends ModelApiRouteTestBase
{
    protected function getRoute(Request $request): AbstractRetrieveModelApiRoute
    {
        return new class() extends AbstractRetrieveModelApiRoute {
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

    public function testRun(): void
    {
        $model = new Person(['id' => 100]);
        $model->refreshWith(['id' => 100, 'name' => 'Bob']);
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
}
