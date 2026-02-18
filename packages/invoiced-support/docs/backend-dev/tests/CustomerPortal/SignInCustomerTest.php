<?php

namespace App\Tests\CustomerPortal;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\CustomerPortal;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SignInCustomerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    private function getSignIn(): SignInCustomer
    {
        $signIn = self::getService('test.sign_in_customer');
        $signIn->setTwig(new Environment(new ArrayLoader()));

        return $signIn;
    }

    public function testSignInCustomer(): void
    {
        $signIn = self::getSignIn();
        $portal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        self::getService('test.customer_portal_context')->set($portal);
        $response = new Response();

        $response2 = $signIn->signIn(self::$customer, $response, true);

        $this->assertEquals($response, $response2);
        $this->assertEquals(self::$customer, $portal->getSignedInCustomer());

        // multiple sign ins should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $signIn->signIn(self::$customer, $response, false);
        }
    }

    public function testSignInCustomerTemporary(): void
    {
        $signIn = self::getSignIn();
        $portal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        self::getService('test.customer_portal_context')->set($portal);

        $this->assertNull($portal->getSignedInCustomer());

        $response = new Response();

        $response2 = $signIn->signIn(self::$customer, $response, true);

        $this->assertEquals($response, $response2);
        $this->assertEquals(self::$customer, $portal->getSignedInCustomer());

        // multiple sign ins should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $signIn->signIn(self::$customer, $response, true);
        }
    }

    public function testSignOut(): void
    {
        $signIn = self::getSignIn();

        $response = new Response();
        $this->assertEquals($response, $signIn->signOut($response));
    }
}
