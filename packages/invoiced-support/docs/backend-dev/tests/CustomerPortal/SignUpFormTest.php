<?php

namespace App\Tests\CustomerPortal;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ValueObjects\Interval;
use App\CustomerPortal\Command\SignUpFormProcessor;
use App\CustomerPortal\Exceptions\SignUpFormException;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\SignUpForm;
use App\CustomerPortal\Models\SignUpPage;
use App\CustomerPortal\Models\SignUpPageAddon;
use App\CustomerPortal\ValueObjects\PrefilledValues;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\AutoPay;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionApproval;
use App\Tests\AppTestCase;
use Mockery;

class SignUpFormTest extends AppTestCase
{
    private static Plan $plan2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
        self::acceptsCreditCards();
        self::acceptsACH();
        self::hasTaxRate();
        self::hasCoupon();
        self::hasCustomField(ObjectType::Subscription->typeName());
        self::hasItem();

        self::$plan2 = new Plan();
        self::$plan2->id = 'monthly';
        self::$plan2->name = 'Monthly Plan';
        self::$plan2->amount = 100;
        self::$plan2->interval = Interval::MONTH;
        self::$plan2->interval_count = 1;
        self::$plan2->saveOrFail();
    }

    protected function tearDown(): void
    {
        $methods = [PaymentMethod::ACH, PaymentMethod::CREDIT_CARD];
        foreach ($methods as $m) {
            $method = PaymentMethod::instance(self::$company, $m);
            if (!$method->enabled) {
                $method->enabled = true;
                $method->save();
            }
        }
    }

    private function getForm(?SignUpPage $page = null): SignUpForm
    {
        if (!$page) {
            $page = new SignUpPage();
        }

        return new SignUpForm($page, self::$company);
    }

    private function getFormProcessor(): SignUpFormProcessor
    {
        return self::getService('test.sign_up_form_processor');
    }

    public function testGetters(): void
    {
        $page = new SignUpPage();
        $form = $this->getForm($page);

        $this->assertEquals($page, $form->getPage());
        $this->assertEquals(self::$company, $form->getCompany());
    }

    public function testSignUpsAllowed(): void
    {
        $form = $this->getForm();

        $this->assertTrue($form->signUpsAllowed());

        // disable credit card payments
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $method->enabled = false;
        $this->assertTrue($method->save());

        $this->assertTrue($form->signUpsAllowed());

        // disable ACH payments
        $method = PaymentMethod::instance(self::$company, PaymentMethod::ACH);
        $method->enabled = false;
        $this->assertTrue($method->save());

        $this->assertFalse($form->signUpsAllowed());
    }

    public function testSignUpsAllowedForCustomer(): void
    {
        $form = $this->getForm();

        $this->assertTrue($form->signUpsAllowedForCustomer(self::$customer));

        // create a subscription
        $subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan,
            ]);

        $this->assertFalse($form->signUpsAllowedForCustomer(self::$customer));

        // sign ups allowed with multiple subscriptions enabled
        $page = new SignUpPage();
        $page->allow_multiple_subscriptions = true;
        $form2 = $this->getForm($page);
        $this->assertTrue($form2->signUpsAllowedForCustomer(self::$customer));

        // cancel the subscription
        self::getService('test.cancel_subscription')->cancel($subscription);
        $this->assertTrue($form->signUpsAllowedForCustomer(self::$customer));
        $this->assertTrue($form2->signUpsAllowedForCustomer(self::$customer));
    }

    public function testGetPrefilledValues(): void
    {
        $page = new SignUpPage();
        $page->custom_fields = [self::$customField->id];
        $form = $this->getForm($page);

        $input = [
            'customer' => [
                'name' => 'test',
            ],
            'shipping' => [
                'country' => 'US',
            ],
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
                'gateway_token' => 'stripe_tok',
            ],
            'plan' => 'basic',
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        $expected = [
            'customer' => [
                'name' => 'test',
            ],
            'shipping' => [
                'country' => 'US',
            ],
            'plan' => 'basic',
            'quantity' => 1,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        $values = $form->getPrefilledValues($input);
        $this->assertInstanceOf(PrefilledValues::class, $values);
        $this->assertEquals($expected, $values->all());
    }

    public function testGetPrefilledValuesCustomer(): void
    {
        $customer = new Customer();
        $customer->name = 'Prefill';
        $customer->address1 = '1234 main st';
        $customer->address2 = 'suite 470';
        $customer->city = 'Austin';
        $customer->state = 'TX';
        $customer->postal_code = '78735';
        $customer->country = 'US';
        $customer->saveOrFail();

        $page = new SignUpPage();
        $page->custom_fields = [self::$customField->id];
        $form = $this->getForm($page);
        $form->setCustomer($customer);

        $input = [
            'customer' => [
                'state' => 'Override',
            ],
            'shipping' => [
                'country' => 'US',
            ],
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
                'gateway_token' => 'stripe_tok',
            ],
            'plan' => 'basic',
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        $expected = [
            'customer' => [
                'name' => 'Prefill',
                'email' => null,
                'address1' => '1234 main st',
                'address2' => 'suite 470',
                'city' => 'Austin',
                'state' => 'Override',
                'postal_code' => '78735',
                'country' => 'US',
                'tax_id' => null,
            ],
            'shipping' => [
                'country' => 'US',
            ],
            'plan' => 'basic',
            'quantity' => 1,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        $values = $form->getPrefilledValues($input);
        $this->assertInstanceOf(PrefilledValues::class, $values);
        $this->assertEquals($expected, $values->all());
    }

    public function testGetPaymentMethods(): void
    {
        $form = $this->getForm();

        $methods = array_keys($form->getPaymentMethods());
        sort($methods);
        $this->assertEquals([PaymentMethod::ACH, PaymentMethod::CREDIT_CARD], $methods);
    }

    public function testGetThanksUrl(): void
    {
        $page = new SignUpPage();
        $page->tenant_id = (int) self::$company->id();
        $page->client_id = '1234';
        $form = $this->getForm($page);
        $customer = new Customer(['id' => 9]);

        $this->assertEquals('http://'.self::$company->username.'.invoiced.localhost:1234/pages/1234/thanks?invoiced_customer_id=9', $form->getThanksUrl($customer, null));

        // try with a customer and subscription
        $customer = new Customer(['id' => 9]);
        $subscription = new Subscription(['id' => 10]);
        $this->assertEquals('http://'.self::$company->username.'.invoiced.localhost:1234/pages/1234/thanks?invoiced_customer_id=9&invoiced_subscription_id=10', $form->getThanksUrl($customer, $subscription));
    }

    public function testGetThanksUrlCustom(): void
    {
        $page = new SignUpPage();
        $page->thanks_url = 'https://example.com/thanks?query=t';
        $form = $this->getForm($page);
        $customer = new Customer(['id' => 9]);

        $this->assertEquals('https://example.com/thanks?query=t&invoiced_customer_id=9', $form->getThanksUrl($customer, null));

        // try with a customer and subscription
        $customer = new Customer(['id' => 9]);
        $subscription = new Subscription(['id' => 10]);
        $this->assertEquals('https://example.com/thanks?query=t&invoiced_customer_id=9&invoiced_subscription_id=10', $form->getThanksUrl($customer, $subscription));
    }

    public function testLookupCoupon(): void
    {
        $page = new SignUpPage();
        $form = $this->getForm($page);

        $this->assertNull($form->lookupCoupon(self::$coupon->id));

        $page->has_coupon_code = true;
        $this->assertNull($form->lookupCoupon('hey'));

        $coupon = $form->lookupCoupon(self::$coupon->id);
        $this->assertInstanceOf(Coupon::class, $coupon);
        $this->assertEquals(self::$coupon->id, $coupon->id);

        $this->assertTrue(self::$coupon->archive());
        $this->assertNull($form->lookupCoupon(self::$coupon->id));
    }

    public function testHandleSubmit(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->name = 'Test';
        $page->tos_url = 'https://invoiced.com/terms';
        $page->shipping_address = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $this->assertTrue($page->save());
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping Name',
                'address1' => 'Shipping Addy 1',
                'address2' => 'Shipping Addy 2',
                'city' => 'Shipping City',
                'state' => 'Shipping State',
                'postal_code' => 'Shipping Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals($page->id(), $customer->sign_up_page_id);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a shipping contact
        $contact = Contact::where('customer_id', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals('Shipping Name', $contact->name);
        $this->assertEquals('Shipping Addy 1', $contact->address1);
        $this->assertEquals('Shipping Addy 2', $contact->address2);
        $this->assertEquals('Shipping City', $contact->city);
        $this->assertEquals('Shipping State', $contact->state);
        $this->assertEquals('Shipping Postal Code', $contact->postal_code);
        $this->assertEquals('US', $contact->country);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertEquals(['test' => 'Some Value'], (array) $subscription->metadata);

        // should create a subscription approval
        $approval = SubscriptionApproval::where('subscription_id', $subscription)->oneOrNull();
        $this->assertInstanceOf(SubscriptionApproval::class, $approval);
        $this->assertEquals('127.0.0.1', $approval->ip);
        $this->assertEquals('firefox', $approval->user_agent);
    }

    public function testHandleSubmitQuantity(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->has_quantity = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'quantity' => 3,
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertEquals(3, $subscription->quantity);
    }

    public function testHandleSubmitCoupon(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->has_coupon_code = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $form = $this->getForm($page);
        self::$coupon->archived = false;
        $this->assertTrue(self::$coupon->save());
        Coupon::setCurrent(self::$coupon);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'coupon' => self::$coupon->id,
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);

        // with a coupon redemption
        $redemptions = $subscription->couponRedemptions();
        $this->assertCount(1, $redemptions);
        $this->assertEquals(self::$coupon->id, $redemptions[0]->coupon);
    }

    public function testHandleSubmitSetupFee(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->shipping_address = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $setupFee = new SignUpPageAddon();
        $setupFee->catalog_item = self::$item->id;
        $setupFee->type = SignUpPageAddon::TYPE_BOOLEAN;
        $setupFee->required = true;
        $page->setAddons([$setupFee]);
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping Name',
                'address1' => 'Shipping Addy 1',
                'address2' => 'Shipping Addy 2',
                'city' => 'Shipping City',
                'state' => 'Shipping State',
                'postal_code' => 'Shipping Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        // Run the tested method
        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a shipping contact
        $contact = Contact::where('customer_id', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals('Shipping Name', $contact->name);
        $this->assertEquals('Shipping Addy 1', $contact->address1);
        $this->assertEquals('Shipping Addy 2', $contact->address2);
        $this->assertEquals('Shipping City', $contact->city);
        $this->assertEquals('Shipping State', $contact->state);
        $this->assertEquals('Shipping Postal Code', $contact->postal_code);
        $this->assertEquals('US', $contact->country);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertEquals(['test' => 'Some Value'], (array) $subscription->metadata);

        // should create a subscription approval
        $approval = SubscriptionApproval::where('subscription_id', $subscription)->oneOrNull();
        $this->assertInstanceOf(SubscriptionApproval::class, $approval);
        $this->assertEquals('127.0.0.1', $approval->ip);
        $this->assertEquals('firefox', $approval->user_agent);

        // should create an invoice with our setup fee
        $invoice = Invoice::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $items = $invoice->items();
        $this->assertEquals(self::$item->id, array_value(end($items), 'catalog_item'));
        $this->assertTrue($invoice->paid);
    }

    public function testHandleSubmitAddons(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $form = $this->getForm($page);

        $addon = new SignUpPageAddon();
        $addon->sign_up_page = (int) $page->id();
        $addon->catalog_item = self::$item->id;
        $addon->recurring = false;

        $addon2 = new SignUpPageAddon();
        $addon2->sign_up_page = (int) $page->id();
        $addon2->catalog_item = self::$item->id;
        $addon2->recurring = true;

        $addon3 = new SignUpPageAddon();
        $addon3->sign_up_page = (int) $page->id();
        $addon3->setPlan(self::$plan);
        $addon3->recurring = true;

        $addons = [$addon, $addon2, $addon3];
        $page->setAddons($addons);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'addons' => [
                'catalog_item-setup-fee' => [
                    'enabled' => false,
                    'quantity' => 10,
                ],
                'catalog_item-'.self::$item->id => [
                    'enabled' => true,
                    'quantity' => 5,
                ],
                'plan-'.self::$plan->id => [
                    'enabled' => true,
                    'quantity' => 2,
                ],
            ],
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);

        // should create addons
        $addons = $subscription->addons();
        $this->assertCount(2, $addons);
        $this->assertEquals(self::$item->id, $addons[0]['catalog_item']);
        $this->assertEquals(5, $addons[0]['quantity']);
        $this->assertEquals(self::$plan->id, $addons[1]['plan']);
        $this->assertEquals(2, $addons[1]['quantity']);

        // should create an invoice with our one-time fee
        $invoice = Invoice::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $items = $invoice->items();
        $this->assertEquals(self::$item->id, array_value(end($items), 'catalog_item'));
        $this->assertTrue($invoice->paid);
    }

    public function testHandleSubmitTrial(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->shipping_address = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $page->trial_period_days = 30;
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping Name',
                'address1' => 'Shipping Addy 1',
                'address2' => 'Shipping Addy 2',
                'city' => 'Shipping City',
                'state' => 'Shipping State',
                'postal_code' => 'Shipping Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a shipping contact
        $contact = Contact::where('customer_id', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals('Shipping Name', $contact->name);
        $this->assertEquals('Shipping Addy 1', $contact->address1);
        $this->assertEquals('Shipping Addy 2', $contact->address2);
        $this->assertEquals('Shipping City', $contact->city);
        $this->assertEquals('Shipping State', $contact->state);
        $this->assertEquals('Shipping Postal Code', $contact->postal_code);
        $this->assertEquals('US', $contact->country);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertGreaterThan(strtotime('+29 days'), $subscription->start_date);
    }

    public function testHandleSubmitTrialSetupFee(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->shipping_address = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $page->trial_period_days = 30;
        $setupFee = new SignUpPageAddon();
        $setupFee->catalog_item = self::$item->id;
        $setupFee->type = SignUpPageAddon::TYPE_BOOLEAN;
        $setupFee->required = true;
        $page->setAddons([$setupFee]);
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping Name',
                'address1' => 'Shipping Addy 1',
                'address2' => 'Shipping Addy 2',
                'city' => 'Shipping City',
                'state' => 'Shipping State',
                'postal_code' => 'Shipping Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a shipping contact
        $contact = Contact::where('customer_id', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals('Shipping Name', $contact->name);
        $this->assertEquals('Shipping Addy 1', $contact->address1);
        $this->assertEquals('Shipping Addy 2', $contact->address2);
        $this->assertEquals('Shipping City', $contact->city);
        $this->assertEquals('Shipping State', $contact->state);
        $this->assertEquals('Shipping Postal Code', $contact->postal_code);
        $this->assertEquals('US', $contact->country);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertGreaterThan(strtotime('+29 days'), $subscription->start_date);

        // should create an invoice with our setup fee
        $invoice = Invoice::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $items = $invoice->items();
        $this->assertEquals(self::$item->id, end($items)['catalog_item']);
        $this->assertTrue($invoice->paid);
    }

    public function testHandleSubmitTrialSetupFeeUnverifiedAch(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->shipping_address = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $page->trial_period_days = 30;
        $setupFee = new SignUpPageAddon();
        $setupFee->catalog_item = self::$item->id;
        $setupFee->type = SignUpPageAddon::TYPE_BOOLEAN;
        $setupFee->required = true;
        $page->setAddons([$setupFee]);
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping Name',
                'address1' => 'Shipping Addy 1',
                'address2' => 'Shipping Addy 2',
                'city' => 'Shipping City',
                'state' => 'Shipping State',
                'postal_code' => 'Shipping Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::ACH,
                'unverified' => true,
                'ach' => true,
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's bank acccount
        $source = $customer->payment_source;
        $this->assertInstanceOf(BankAccount::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);
        $this->assertTrue($source->needsVerification());

        // should create a shipping contact
        $contact = Contact::where('customer_id', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals('Shipping Name', $contact->name);
        $this->assertEquals('Shipping Addy 1', $contact->address1);
        $this->assertEquals('Shipping Addy 2', $contact->address2);
        $this->assertEquals('Shipping City', $contact->city);
        $this->assertEquals('Shipping State', $contact->state);
        $this->assertEquals('Shipping Postal Code', $contact->postal_code);
        $this->assertEquals('US', $contact->country);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertGreaterThan(strtotime('+29 days'), $subscription->start_date);

        // should create an unpaid, AutoPay invoice with our setup fee
        $invoice = Invoice::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $items = $invoice->items();
        $this->assertEquals(self::$item->id, end($items)['catalog_item']);
        $this->assertTrue($invoice->autopay);
        $this->assertFalse($invoice->paid);
    }

    public function testHandleSubmitCalendarBilling(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $page->shipping_address = true;
        $page->tenant_id = (int) self::$company->id();
        $page->taxes = [self::$taxRate->id];
        $page->snap_to_nth_day = 1;
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping Name',
                'address1' => 'Shipping Addy 1',
                'address2' => 'Shipping Addy 2',
                'city' => 'Shipping City',
                'state' => 'Shipping State',
                'postal_code' => 'Shipping Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan2->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a shipping contact
        $contact = Contact::where('customer_id', $customer->id())->oneOrNull();
        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertEquals('Shipping Name', $contact->name);
        $this->assertEquals('Shipping Addy 1', $contact->address1);
        $this->assertEquals('Shipping Addy 2', $contact->address2);
        $this->assertEquals('Shipping City', $contact->city);
        $this->assertEquals('Shipping State', $contact->state);
        $this->assertEquals('Shipping Postal Code', $contact->postal_code);
        $this->assertEquals('US', $contact->country);

        // should create a subscription using calendar billing
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan2->id, $subscription->plan);
        $this->assertEquals([self::$taxRate->id], $subscription->taxes);
        $this->assertGreaterThan(time(), $subscription->renews_next);
        $this->assertEquals(1, date('j', $subscription->renews_next));
    }

    public function testHandleSubmitExistingCustomer(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->name = 'Test';
        $page->tenant_id = (int) self::$company->id();
        $this->assertTrue($page->save());
        $form = $this->getForm($page);
        $form->setCustomer(self::$customer);

        $parameters = [
            'customer' => [
                'address1' => '5301 Southwest Parkway',
                'address2' => 'Suite 470',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78735',
            ],
            'tos_accepted' => true,
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => '__default__',
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(self::$customer, $customer);
        $this->assertEquals('5301 Southwest Parkway', $customer->address1);
        $this->assertEquals('Suite 470', $customer->address2);
        $this->assertEquals('Austin', $customer->city);
        $this->assertEquals('TX', $customer->state);
        $this->assertEquals('78735', $customer->postal_code);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
    }

    public function testHandleSubmitExistingCustomerNewPaymentInfo(): void
    {
        // Setup

        self::$customer->clearDefaultPaymentSource();

        $page = new SignUpPage();
        $page->name = 'Test';
        $page->tenant_id = (int) self::$company->id();
        $this->assertTrue($page->save());
        $form = $this->getForm($page);
        $form->setCustomer(self::$customer);

        $parameters = [
            'tos_accepted' => true,
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(self::$customer, $customer);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);

        // should create a subscription
        $subscription = Subscription::where('customer', $customer)->oneOrNull();
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(self::$plan->id, $subscription->plan);
    }

    public function testHandleSubmitToSFail(): void
    {
        $this->expectException(SignUpFormException::class);
        $this->expectExceptionMessage('Please accept the Terms of Service in order to sign up.');

        $page = new SignUpPage();
        $page->tos_url = 'https://invoiced.com/terms';
        $form = $this->getForm($page);

        $parameters = [];

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');
    }

    public function testHandleSubmitCustomerFail(): void
    {
        $this->expectException(SignUpFormException::class);
        $this->expectExceptionMessage('Unable to create customer profile: Name is missing');

        $form = $this->getForm();

        $parameters = [];

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');
    }

    public function testHandleSubmitShippingFail(): void
    {
        $this->expectException(SignUpFormException::class);
        $this->expectExceptionMessage('Unable to create shipping contact: Name is missing');

        $page = new SignUpPage();
        $page->shipping_address = true;
        $form = $this->getForm($page);

        $parameters = [
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
        ];

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');
    }

    public function testHandleSubmitSubscriptionFail(): void
    {
        $this->expectException(SignUpFormException::class);
        $this->expectExceptionMessage('Unable to create subscription: Plan missing');

        $form = $this->getForm();

        $parameters = [
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
        ];

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');
    }

    public function testHandleSubmitSetupFeeFail(): void
    {
        $this->expectException(SignUpFormException::class);
        $this->expectExceptionMessage('Unable to collect upfront charge: Charge declined');

        $autoPay = Mockery::mock(AutoPay::class);
        $autoPay->shouldReceive('collect')
            ->andThrow(new AutoPayException('Charge declined'));

        $vaultPaymentInfo = Mockery::mock(VaultPaymentInfo::class);
        $vaultPaymentInfo->shouldReceive('save');

        $notificationSpool = Mockery::mock(NotificationSpool::class);

        $customerPortalEvents = Mockery::mock(CustomerPortalEvents::class);

        $page = new SignUpPage();
        $page->tenant_id = (int) self::$company->id();
        $page->trial_period_days = 14;
        $setupFee = new SignUpPageAddon();
        $setupFee->catalog_item = self::$item->id;
        $setupFee->type = SignUpPageAddon::TYPE_BOOLEAN;
        $setupFee->required = true;
        $page->setAddons([$setupFee]);
        $form = $this->getForm($page);

        $parameters = [
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'plan' => self::$plan->id,
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        $processor = new SignUpFormProcessor($autoPay, $vaultPaymentInfo, self::getService('test.create_subscription'), self::getService('test.transaction_manager'), $notificationSpool, $customerPortalEvents);

        $processor->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');
    }

    public function testHandleSubmitAutoPay(): void
    {
        // Setup

        $page = new SignUpPage();
        $page->name = 'Test';
        $page->type = SignUpPage::TYPE_AUTOPAY;
        $page->tos_url = 'https://invoiced.com/terms';
        $page->tenant_id = (int) self::$company->id();
        $this->assertTrue($page->save());
        $form = $this->getForm($page);

        $parameters = [
            'tos_accepted' => true,
            'customer' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'address1' => 'Addy 1',
                'address2' => 'Addy 2',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country' => 'US',
            ],
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
            'metadata' => [
                'test' => 'Some Value',
            ],
        ];

        // Run the tested method

        /** @var Customer $customer */
        [$customer] = $this->getFormProcessor()->handleSubmit($form, $parameters, '127.0.0.1', 'firefox');

        // Verify results

        // verify the customer
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->persisted());
        $this->assertTrue($customer->autopay);
        $this->assertEquals($page->id(), $customer->sign_up_page_id);
        $this->assertEquals('Test', $customer->name);
        $this->assertEquals('test@example.com', $customer->email);
        $this->assertEquals('Addy 1', $customer->address1);
        $this->assertEquals('Addy 2', $customer->address2);
        $this->assertEquals('City', $customer->city);
        $this->assertEquals('State', $customer->state);
        $this->assertEquals('Postal Code', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // verify the customer's card
        $source = $customer->payment_source;
        $this->assertInstanceOf(Card::class, $source);
        $this->assertEquals(MockGateway::ID, $source->gateway);
        $this->assertNotNull($source->gateway_id);
    }
}
