<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\Orm\Error;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Tests\Core\RestApi\Post;
use Exception;
use Symfony\Component\HttpFoundation\Request;

abstract class ModelApiRouteTestBase extends ApiRouteTestBase
{
    abstract protected function getRoute(Request $request): AbstractModelApiRoute;

    public function testGetModelId(): void
    {
        $route = $this->getRoute(new Request());
        $route->setModelId('10');
        $this->assertEquals(10, $route->getModelId());
    }

    public function testGetModel(): void
    {
        $request = Request::create('/');
        $request->attributes->set('model', Post::class);
        $route = $this->getRoute($request);
        self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $model = new Post();
        $route->setModel($model);
        $this->assertEquals($model, $route->getModel());

        // try with model class name
        $this->assertEquals($route, $route->setModelClass(Post::class));
        $this->assertInstanceOf(Post::class, $route->getModel());
    }

    public function testNotFound(): void
    {
        $request = Request::create('https://example.com/api/users', 'post');
        $route = $this->getRoute($request);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        try {
            $route->buildResponse($context);
            throw new Exception('Should not reach this point');
        } catch (InvalidRequest $e) {
            $this->assertEquals('Request was not recognized: POST /api/users', $e->getMessage());
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    public function testGetFirstError(): void
    {
        $route = $this->getRoute(new Request());
        $this->assertNull($route->getFirstError());

        $model = new Post();
        $errors = $model->getErrors();
        $errors->add('Test');
        $errors->add('Test 2');
        $errors->add('Test 3');
        $route->setModel($model);

        $expected = new Error('Test', [], 'Test');
        $this->assertEquals($expected, $route->getFirstError());
    }

    public function testHumanClassName(): void
    {
        $route = $this->getRoute(new Request());
        $this->assertEquals('Post', $route->humanClassName('App\Posts\Models\Post'));
        $this->assertEquals('Line Item', $route->humanClassName('App\AccountsReceivable\Models\LineItem'));
        $error = new InvalidRequest('error');
        $this->assertEquals('Invalid Request', $route->humanClassName($error));
    }
}
