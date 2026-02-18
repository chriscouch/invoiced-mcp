<?php

namespace App\Tests\PaymentProcessing\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\Utils\SimpleCache;
use App\PaymentProcessing\Api\ListPaymentSourcesRoute;
use App\PaymentProcessing\Gateways\MockGateway;
use App\Tests\AppTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

class ListPaymentSourceRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasCard(MockGateway::ID);
        self::$card->chargeable = false;
        self::$card->saveOrFail();
        self::hasBankAccount(MockGateway::ID);
    }

    public function testResponse(): void
    {
        $request = new Request();
        $request->attributes->set('model_id', self::$customer->id);

        $route = new ListPaymentSourcesRoute(new ApiCache(new ArrayAdapter(), new SimpleCache(new ArrayAdapter())));

        $response = self::getService('test.api_runner')->run($route, $request);
        $this->assertEquals(1, $response->headers->get('X-Total-Count'));
        $this->assertEquals('<http://api.invoiced.localhost:1234?page=1&sort=&paginate=offset&include_hidden=0>; rel="self", <http://api.invoiced.localhost:1234?page=1&sort=&paginate=offset&include_hidden=0>; rel="first", <http://api.invoiced.localhost:1234?page=1&sort=&paginate=offset&include_hidden=0>; rel="last"', $response->headers->get('Link'));

        $request->query->set('include_hidden', 1);
        $response = self::getService('test.api_runner')->run($route, $request);
        $this->assertEquals(2, $response->headers->get('X-Total-Count'));
        $this->assertEquals('<http://api.invoiced.localhost:1234?page=1&sort=&paginate=offset&include_hidden=1>; rel="self", <http://api.invoiced.localhost:1234?page=1&sort=&paginate=offset&include_hidden=1>; rel="first", <http://api.invoiced.localhost:1234?page=1&sort=&paginate=offset&include_hidden=1>; rel="last"', $response->headers->get('Link'));
    }
}
