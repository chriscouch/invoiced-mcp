<?php

namespace App\Tests\Controller;

use App\EntryPoint\Controller\CustomerPortal\SubscriptionCustomerPortalController;
use App\SubscriptionBilling\Operations\CancelSubscription;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionCustomerPortalControllerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCancelSubscription(): void
    {
        $controller = Mockery::mock(SubscriptionCustomerPortalController::class);
        $controller->shouldAllowMockingProtectedMethods()->makePartial();
        self::hasCustomer();
        self::hasPlan();
        self::hasSubscription();

        $cancelSubscription = Mockery::mock(CancelSubscription::class);
        $cancelSubscription->shouldReceive('cancel');

        $controller->shouldReceive('isCsrfTokenValid')->andReturn(true);

        $controller->shouldReceive('render');
        $controller->cancelSubscription(new Request(), $cancelSubscription, self::getService('test.database'), self::$subscription->client_id);
    }
}
