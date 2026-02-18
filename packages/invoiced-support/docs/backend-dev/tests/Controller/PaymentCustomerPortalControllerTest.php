<?php

namespace App\Tests\Controller;

use App\EntryPoint\Controller\CustomerPortal\PaymentCustomerPortalController;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class PaymentCustomerPortalControllerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testExpectedPaymentThanks(): void
    {
        $controller = Mockery::mock(PaymentCustomerPortalController::class);
        $controller->shouldAllowMockingProtectedMethods()->makePartial();
        $request = new Request();
        $request->query->set('customer', self::$customer->client_id);

        $controller->shouldReceive('render')->once();

        $controller->expectedPaymentThanks($request);
    }
}
