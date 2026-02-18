<?php

namespace App\Tests\Integrations\Api;

use App\Integrations\Api\ListIntegrationsRoute;
use App\Integrations\Libs\IntegrationFactory;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class ListIntegrationsRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testBuildResponse(): void
    {
        $request = new Request();

        $route = new ListIntegrationsRoute(new IntegrationFactory(), self::getService('test.tenant'));
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'intacct' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'netsuite' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'quickbooks_desktop' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'quickbooks_online' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'avalara' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'lob' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'slack' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'twilio' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'xero' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'earth_class_mail' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'chartmogul' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'business_central' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'freshbooks' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'wave' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
            'sage_accounting' => [
                'connected' => false,
                'name' => null,
                'extra' => (object) [],
            ],
        ];

        $this->assertEquals($expected, $route->buildResponse($context));
    }
}
