<?php

namespace App\Tests\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\InvoiceChasingCadence;
use App\AccountsReceivable\Api\CreateInvoiceRoute;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CreateInvoiceRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testDeliveryWithoutCadenceId(): void
    {
        $request = new Request([], [
            'customer' => self::$customer->id,
            'delivery' => [
                'emails' => 'test@test.com',
            ],
            'items' => [
                [
                    'name' => 'Test Item',
                    'description' => 'test',
                    'quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $route = new CreateInvoiceRoute();
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->setModelClass(Invoice::class);
        $resp = $route->buildResponse($context);
        $delivery = InvoiceDelivery::where('invoice_id', $resp->id)->one();
        $this->assertEquals(null, $delivery->cadence_id);
        $this->assertEquals('test@test.com', $delivery->emails);
    }

    public function testDeliveryWithCadenceId(): void
    {
        $request = new Request([], [
            'customer' => self::$customer->id,
            'delivery' => [
                'emails' => 'test@test.com',
                'chase_schedule' => [
                    [
                        'trigger' => InvoiceChasingCadence::ON_ISSUE,
                        'options' => [
                            'hour' => 3,
                            'email' => true,
                            'sms' => false,
                            'letter' => false,
                        ],
                    ],
                ],
            ],
            'items' => [
                [
                    'name' => 'Test Item',
                    'description' => 'test',
                    'quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $route = new CreateInvoiceRoute();
        $route->setModelClass(Invoice::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $resp = $route->buildResponse($context);
        $delivery = InvoiceDelivery::where('invoice_id', $resp->id)->one();
        $this->assertEquals([
            'hour' => 3,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ], $delivery->chase_schedule[0]['options']);
        $this->assertEquals('test@test.com', $delivery->emails);
    }

    public function testDeliveryWithDefaultCadenceId(): void
    {
        $cadence1 = new InvoiceChasingCadence();
        $cadence1->name = 'Chasing Cadence';
        $cadence1->default = true;
        $cadence1->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $cadence1->saveOrFail();

        $request = new Request([], [
            'customer' => self::$customer->id,
            'delivery' => [
                'emails' => 'test@test.com',
            ],
            'items' => [
                [
                    'name' => 'Test Item',
                    'description' => 'test',
                    'quantity' => 1,
                    'unit_cost' => 100,
                ],
            ],
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $route = new CreateInvoiceRoute();
        $route->setModelClass(Invoice::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $resp = $route->buildResponse($context);
        $delivery = InvoiceDelivery::where('invoice_id', $resp->id)->one();
        $this->assertEquals([
            'hour' => 4,
            'email' => true,
            'sms' => false,
            'letter' => false,
        ], $delivery->chase_schedule[0]['options']);
        $this->assertEquals('test@test.com', $delivery->emails);
    }
}
