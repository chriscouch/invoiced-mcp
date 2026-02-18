<?php

namespace App\Tests\AccountsReceivable\Api;

use App\AccountsReceivable\Api\BalanceRoute;
use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class BalanceRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    private function getRoute(): BalanceRoute
    {
        return new BalanceRoute(new CustomerBalanceGenerator(self::getService('test.database')));
    }

    public function testBuildResponse(): void
    {
        $request = new Request();
        $route = $this->getRoute();
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->setModel(self::$customer);

        $expected = [
            'available_credits' => 0.0,
            'currency' => 'usd',
            'history' => [],
            'total_outstanding' => 0.0,
            'past_due' => false,
            'due_now' => 0.0,
            'open_credit_notes' => 0.0,
        ];

        $this->assertEquals($expected, $route->buildResponse($context));
    }
}
