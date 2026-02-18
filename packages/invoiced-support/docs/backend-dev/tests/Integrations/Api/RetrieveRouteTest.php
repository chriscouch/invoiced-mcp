<?php

namespace App\Tests\Integrations\Api;

use App\Integrations\Api\RetrieveIntegrationRoute;
use App\Integrations\Libs\IntegrationFactory;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class RetrieveRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getRoute(): RetrieveIntegrationRoute
    {
        return new RetrieveIntegrationRoute(new IntegrationFactory(), self::getService('test.tenant'));
    }

    public function testBuildResponse(): void
    {
        $request = new Request();
        $request->attributes->set('id', 'xero');
        $route = $this->getRoute();
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'connected' => false,
            'name' => null,
            'extra' => (object) [
                'sync_profile' => null,
            ],
        ];

        $this->assertEquals($expected, $route->buildResponse($context));
    }
}
