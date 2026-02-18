<?php

namespace App\Tests\AccountsReceivable\Api;

use App\AccountsReceivable\Api\VoidInvoiceRoute;
use App\AccountsReceivable\Models\Invoice;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class VoidDocumentRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testRoute(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $route = new VoidInvoiceRoute();
        $context = self::getService('test.api_runner')->validateRequest(new Request(), $route->getDefinition());
        $route->setModel(self::$invoice);

        $response = $route->buildResponse($context);
        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertTrue($response->voided);
    }
}
