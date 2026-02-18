<?php

namespace App\Tests\PaymentProcessing\Api;

use App\PaymentProcessing\Api\DeletePaymentSourceRoute;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class DeletePaymentSourceRouteTest extends AppTestCase
{
    public function testParse(): void
    {
        $request = new Request();

        $route = new DeletePaymentSourceRoute(Mockery::mock(DeletePaymentInfo::class));
        $route->setModelClass(Card::class);

        $this->assertInstanceOf(Card::class, $route->getModel());
    }
}
