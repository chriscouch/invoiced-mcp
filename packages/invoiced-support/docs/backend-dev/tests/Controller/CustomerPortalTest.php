<?php

namespace App\Tests\Controller;

use App\Core\Entitlements\Models\Product;
use App\Companies\Models\Company;
use App\Tests\AppTestCase;
use App\Themes\Models\Template;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomerPortalTest extends WebTestCase
{
    private static string $username;
    private static Company $company;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$username = 'test'.time().rand();
        self::$company = new Company();
        self::$company->name = 'Test';
        self::$company->username = self::$username;
        self::$company->saveOrFail();
        // enable all modules
        foreach (Product::first(100) as $product) {
            self::$company->features->enableProduct($product);
        }

        // clear the cache to prevent any captcha verification
        $k = 'invoicedtest:billing_portal_views.127.0.0.1';
        AppTestCase::getKernel()->getContainer()->get('test.redis')->del($k); /* @phpstan-ignore-line */
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // The next test suite that runs should reboot the kernel
        // because a kernel was booted inside of this test suite
        AppTestCase::$rebootKernel = true;

        self::$company->delete();
    }

    public function testJavascript(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://'.self::$username.'.invoiced.localhost:1234/_js');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('application/javascript', $response->headers->get('Content-Type'));

        $template = new Template();
        $template->filename = 'billing_portal/index.js';
        $template->content = 'console.log("hello world")';
        $template->saveOrFail();

        $client->request('GET', 'http://'.self::$username.'.invoiced.localhost:1234/_js');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('console.log("hello world")', $response->getContent());
    }

    public function testCss(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://'.self::$username.'.invoiced.localhost:1234/_css');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('text/css; charset=UTF-8', $response->headers->get('Content-Type'));

        $template = new Template();
        $template->filename = 'billing_portal/styles.css';
        $template->content = 'body { background: red; }';
        $template->saveOrFail();

        $client->request('GET', 'http://'.self::$username.'.invoiced.localhost:1234/_css');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('body { background: red; }', $response->getContent());
    }

    public function testLoginForm(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://'.self::$username.'.invoiced.localhost:1234/login');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCustomDomain(): void
    {
        self::$company->custom_domain = 'billing.dundermifflin.com';
        self::$company->saveOrFail();

        $client = static::createClient();

        $client->request('GET', 'http://billing.dundermifflin.com:1234/login');

        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCustomDomainLandingRedirect(): void
    {
        $client = static::createClient();

        $client->request('GET', 'http://billing.dundermifflin.com:1234/');

        $response = $client->getResponse();
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->headers->get('Location'));
    }
}
