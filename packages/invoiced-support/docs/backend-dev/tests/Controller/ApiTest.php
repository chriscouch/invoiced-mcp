<?php

namespace App\Tests\Controller;

use App\Tests\AppTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTest extends WebTestCase
{
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // The next test suite that runs should reboot the kernel
        // because a kernel was booted inside of this test suite
        AppTestCase::$rebootKernel = true;
    }

    public function testUnauthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://api.invoiced.localhost:1234/');

        $response = $client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('{"type":"invalid_request","message":"Missing API key! HINT: You can pass in your API key as the username parameter using HTTP Basic Auth."}', $response->getContent());
        $this->assertEquals('application/json', $response->headers->get('content-type'));
        $this->assertEquals('Basic realm="Invoiced"', $response->headers->get('www-authenticate'));
    }

    public function testInvalidAuthenticationUsernameOnly(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'INVALID',
            'PHP_AUTH_PW' => '',
        ]);

        $client->request('GET', 'http://api.invoiced.localhost:1234/');

        $response = $client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('{"type":"invalid_request","message":"We could not authenticate you with the API Key: INV*LID"}', $response->getContent());
        $this->assertEquals('application/json', $response->headers->get('content-type'));
        $this->assertEquals('Basic realm="Invoiced"', $response->headers->get('www-authenticate'));
    }

    public function testInvalidAuthenticationUsernamePassword(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'INVALID',
            'PHP_AUTH_PW' => 'PASSWORD',
        ]);

        $client->request('GET', 'http://api.invoiced.localhost:1234/');

        $response = $client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('{"type":"invalid_request","message":"We did not find an account matching the username: INVALID"}', $response->getContent());
        $this->assertEquals('application/json', $response->headers->get('content-type'));
        $this->assertEquals('Basic realm="Invoiced"', $response->headers->get('www-authenticate'));
    }

    public function testInvalidAuthenticationDashboard(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'INVALID',
            'PHP_AUTH_PW' => 'PASSWORD',
            'HTTP_X_APP_VERSION' => '123456',
        ]);

        $client->request('GET', 'http://api.invoiced.localhost:1234/');

        $response = $client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('{"type":"invalid_request","message":"We did not find an account matching the username: INVALID"}', $response->getContent());
        $this->assertEquals('application/json', $response->headers->get('content-type'));
        $this->assertFalse($response->headers->has('www-authenticate'));
    }

    public function testHealth(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://api.invoiced.localhost:1234/health');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }
}
