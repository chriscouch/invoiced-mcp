<?php

namespace App\Tests\Core\Auth\OAuth;

use App\Core\Authentication\OAuth\OAuthServerFactory;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuthServerFactoryTest extends AppTestCase
{
    private function getFactory(): OAuthServerFactory
    {
        return self::getService('test.oauth_server_factory');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetAuthorizationServer(): void
    {
        $this->getFactory()->getAuthorizationServer();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGetResourceServer(): void
    {
        $this->getFactory()->getResourceServer();
    }

    public function testConvertRequestToPsr(): void
    {
        $factory = $this->getFactory();
        $psrRequest = $factory->convertRequestToPsr(new Request(['test' => true], [], [], [], [], ['HTTP_HOST' => 'localhost'], 'Content'));
        $this->assertTrue($psrRequest->getQueryParams()['test']);
    }

    public function testConvertResponseToPsr(): void
    {
        $factory = $this->getFactory();
        $psrResponse = $factory->convertResponseToPsr(new Response('Content', 401));
        $this->assertEquals(401, $psrResponse->getStatusCode());
    }
}
