<?php

namespace App\Tests\Controller;

use App\Tests\AppTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LandingTest extends WebTestCase
{
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // The next test suite that runs should reboot the kernel
        // because a kernel was booted inside of this test suite
        AppTestCase::$rebootKernel = true;
    }

    public function testIndex(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://invoiced.localhost:1234/');

        $response = $client->getResponse();
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('https://www.invoiced.com/', $response->headers->get('Location'));
    }

    public function testProductRedirect(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://invoiced.localhost:1234/product/invoice-to-cash');

        $response = $client->getResponse();
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('https://www.invoiced.com/product/invoice-to-cash', $response->headers->get('Location'));
    }
}
