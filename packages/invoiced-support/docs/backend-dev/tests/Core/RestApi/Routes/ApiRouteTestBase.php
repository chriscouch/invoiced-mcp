<?php

namespace App\Tests\Core\RestApi\Routes;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

abstract class ApiRouteTestBase extends AppTestCase
{
    abstract public function testRun(): void;

    abstract protected function getRoute(Request $request): AbstractApiRoute;
}
