<?php

namespace App\Tests\Core;

use App\Core\DomainRouter;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class DomainRouterTest extends AppTestCase
{
    public function testDetermineRouteFromHost(): void
    {
        $router = $this->getRouter();

        $this->assertEquals(['main', false], $router->determineRouteFromHost(''));
        $this->assertEquals(['main', false], $router->determineRouteFromHost('invoiced.localhost'));

        $this->assertEquals(['unknown', 'mail'], $router->determineRouteFromHost('mail.invoiced.localhost'));
        $this->assertEquals(['unknown', 'payments'], $router->determineRouteFromHost('payments.invoiced.localhost'));

        $this->assertEquals(['billing_portal', 'acme'], $router->determineRouteFromHost('acme.invoiced.localhost'));

        $this->assertEquals(['billing_portal', 'custom:blah'], $router->determineRouteFromHost('blah'));

        $this->assertEquals(['billing_portal', 'custom:billing.example.com'], $router->determineRouteFromHost('billing.example.com'));

        $this->assertEquals(['api', 'api'], $router->determineRouteFromHost('api.invoiced.localhost'));

        $this->assertEquals(['tknz', 'tknz.invoiced.localhost'], $router->determineRouteFromHost('tknz.invoiced.localhost'));

        $this->assertEquals(['files', 'files.invoiced.localhost'], $router->determineRouteFromHost('files.invoiced.localhost'));
    }

    public function testRouteMain(): void
    {
        $request = new Request();
        $router = $this->getRouter();

        $router->route($request);

        $this->assertEquals('main', $request->attributes->get('domain'));
        $this->assertFalse($request->attributes->get('subdomain'));
    }

    public function testRouteApi(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_HOST' => 'api.invoiced.localhost']);
        $router = $this->getRouter();

        $router->route($request);

        $this->assertEquals('api', $request->attributes->get('domain'));
        $this->assertEquals('api', $request->attributes->get('subdomain'));
    }

    public function testRouteCustomerPortal(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_HOST' => 'acme.invoiced.localhost']);
        $router = $this->getRouter();

        $router->route($request);

        $this->assertEquals('billing_portal', $request->attributes->get('domain'));
        $this->assertEquals('acme', $request->attributes->get('subdomain'));
    }

    public function testRouteUnknown(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_HOST' => 'payments.invoiced.localhost']);
        $router = $this->getRouter();

        $router->route($request);

        $this->assertEquals('unknown', $request->attributes->get('domain'));
        $this->assertEquals('payments', $request->attributes->get('subdomain'));
    }

    private function getRouter(): DomainRouter
    {
        return new DomainRouter(self::getService('test.login_helper'), 'test', 'invoiced.localhost');
    }
}
