<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountRouting;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use InvalidArgumentException;

class PaymentRouterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    /**
     * Tests case when no merchant account is found.
     */
    public function testNoMerchantAccount(): void
    {
        $router = new PaymentRouter();
        $method = new PaymentMethod();
        $method->id = PaymentMethod::CREDIT_CARD;
        $method->gateway = 'authorizenet';
        $this->assertNull($router->getMerchantAccount($method, null, []));

        // assert exception
        $this->expectException(InvalidArgumentException::class);
        $this->assertNull($router->getMerchantAccount($method, null, [], true));
    }

    /**s
     * Asserts that merchant account is retrieved from payment
     * method details.
     */
    public function testFromPaymentMethod(): void
    {
        $router = new PaymentRouter();
        $method = new PaymentMethod();
        $method->id = PaymentMethod::CREDIT_CARD;
        $method->gateway = MockGateway::ID; // Mock gateway will create a merchant account on getDefaultMerchantAccount

        /** @var MerchantAccount $merchantAccount */
        $merchantAccount = $router->getMerchantAccount($method, null, []);
        $this->assertNotNull($merchantAccount);
        $this->assertEquals(MockGateway::ID, $merchantAccount->gateway);
    }

    /**
     * Asserts that merchant account is retrieved from customer
     * details.
     */
    public function testFromCustomer(): void
    {
        $router = new PaymentRouter();
        $method = new PaymentMethod();
        $method->id = PaymentMethod::CREDIT_CARD;
        $method->gateway = 'authorizenet';

        $merchantAccount = new MerchantAccount();
        $merchantAccount->name = 'Account: stripe';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->gateway = StripeGateway::ID;
        $merchantAccount->gateway_id = 'xxxxxxxxxxxxxxxxxx';
        $merchantAccount->credentials = (object) ['secret' => 'xxxxxxxxxxxxxxxxxx'];
        $merchantAccount->saveOrFail();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->email = 'test@router-test.com';
        $customer->cc_gateway = $merchantAccount;
        $customer->saveOrFail();

        /** @var MerchantAccount $result */
        $result = $router->getMerchantAccount($method, $customer, []);
        $this->assertNotNull($result);
        $this->assertEquals($merchantAccount->id(), $result->id());
        $this->assertEquals(StripeGateway::ID, $result->gateway);
    }

    /**
     * Asserts that merchant account is retrieved from invoice
     * details.
     */
    public function testFromDocuments(): void
    {
        $router = new PaymentRouter();
        $method = new PaymentMethod();
        $method->id = PaymentMethod::CREDIT_CARD;
        $method->gateway = 'authorizenet';

        $merchantAccount = new MerchantAccount();
        $merchantAccount->name = 'Account: stripe';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->gateway = StripeGateway::ID;
        $merchantAccount->gateway_id = 'xxxxxxxxxxxxxxxxxx';
        $merchantAccount->credentials = (object) ['secret' => 'xxxxxxxxxxxxxxxxxx'];
        $merchantAccount->saveOrFail();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->email = 'test@router-test.com';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();

        $merchantAccountRouting = new MerchantAccountRouting();
        $merchantAccountRouting->method = PaymentMethod::CREDIT_CARD;
        $merchantAccountRouting->invoice_id = $invoice->id;
        $merchantAccountRouting->merchant_account_id = $merchantAccount->id;
        $merchantAccountRouting->saveOrFail();

        /** @var MerchantAccount $result */
        $result = $router->getMerchantAccount($method, $customer, [$invoice]);
        $this->assertNotNull($result);
        $this->assertEquals($merchantAccount->id(), $result->id());
        $this->assertEquals(StripeGateway::ID, $result->gateway);
    }
}
