<?php

namespace App\Tests\CustomerPortal\Command;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Libs\PaymentLinkHelper;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\AccountsReceivable\Models\ShippingDetail;
use App\CashApplication\Models\Payment;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Command\PaymentLinkProcessor;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentLinkProcessorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasCard(TestGateway::ID);
    }

    private function getAction(): PaymentLinkProcessor
    {
        return self::getService('test.payment_link_processor');
    }

    private function makePaymentLink(array $values = []): PaymentLink
    {
        $paymentLink = new PaymentLink();
        $paymentLink->status = PaymentLinkStatus::Active;
        $paymentLink->currency = 'usd';
        foreach ($values as $key => $value) {
            $paymentLink->{$key} = $value;
        }
        $paymentLink->saveOrFail();

        return $paymentLink;
    }

    private function makePaymentLinkField(PaymentLink $paymentLink, array $values): PaymentLinkField
    {
        $field = new PaymentLinkField();
        $field->payment_link = $paymentLink;
        $field->required = true;
        foreach ($values as $key => $value) {
            $field->{$key} = $value;
        }
        $field->saveOrFail();

        return $field;
    }

    private function makeCustomField(ObjectType $objectType, string $id): CustomField
    {
        $customField = new CustomField();
        $customField->id = $id;
        $customField->object = $objectType->typeName();
        $customField->name = $id;
        $customField->saveOrFail();

        return $customField;
    }

    private function makePaymentFlow(PaymentLink $paymentLink): PaymentFlow
    {
        $manager = self::getService('test.payment_flow_manager');
        $flow = new PaymentFlow();
        $flow->payment_link = $paymentLink;
        $flow->amount = 0;
        $flow->currency = $paymentLink->currency;
        $flow->customer = $paymentLink->customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $manager->create($flow);

        return $flow;
    }

    public function testHandleSubmitNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        PaymentLinkHelper::getPaymentLink(self::getService('translator'), 'DOESNOTEXIST', '');
    }

    public function testHandleSubmit(): void
    {
        $action = $this->getAction();

        $paymentLink = $this->makePaymentLink([
            'reusable' => true,
            'collect_shipping_address' => true,
        ]);
        $paymentFlow = $this->makePaymentFlow($paymentLink);

        $parameters = [
            'payment_flow' => $paymentFlow->identifier,
            'amount' => 100,
            'client_id' => 'CLIENTID',
            'email' => 'test@test.com',
            'company' => '',
            'first_name' => '',
            'last_name' => '',
            'phone' => '5121234567',
            'address' => [
                'address1' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'NY',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            'shipping' => [
                'name' => 'Shipping',
                'address1' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'NY',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        // new customer
        EventSpool::enable();
        $result = $action->handleSubmit($paymentLink, $parameters);

        $customer = $result->getCustomer();
        $this->assertEquals('CLIENTID', $customer->name);
        $this->assertEquals('123 Main St', $customer->address1);
        $this->assertEquals('Anytown', $customer->city);
        $this->assertEquals('NY', $customer->state);
        $this->assertEquals('12345', $customer->postal_code);
        $this->assertEquals('US', $customer->country);
        $this->assertEquals('5121234567', $customer->phone);

        $invoice = $result->getInvoice();
        $this->assertEquals(100, $invoice->total);
        $this->assertEquals('usd', $invoice->currency);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('Payment Link', $invoice->items[0]->name);
        $this->assertEquals($customer->id(), $invoice->customer);

        $shipTo = $invoice->ship_to;
        $this->assertInstanceOf(ShippingDetail::class, $shipTo);
        $this->assertEquals('Shipping', $shipTo->name);
        $this->assertEquals('123 Main St', $shipTo->address1);
        $this->assertEquals('Anytown', $shipTo->city);
        $this->assertEquals('NY', $shipTo->state);
        $this->assertEquals('12345', $shipTo->postal_code);
        $this->assertEquals('US', $shipTo->country);

        $payment = $result->getPayment();
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $payment->method);
        $this->assertEquals('usd', $payment->currency);
        $this->assertEquals(100, $payment->amount);

        $paymentFlow = $result->getPaymentFlow();
        $this->assertEquals('usd', $paymentFlow->currency);
        $this->assertEquals(100, $paymentFlow->amount);
        $this->assertEquals(PaymentFlowStatus::Succeeded, $paymentFlow->status);

        $this->assertHasEvent($paymentLink, EventType::PaymentLinkCompleted);

        // new customer different parameters
        $paymentLink->terms_of_service_url = 'https://example.com';
        $parameters['company'] = 'test test';
        $parameters['client_id'] = 'CLIENTID2';
        $paymentLink->currency = 'eur';
        $paymentLink->saveOrFail();
        $paymentFlow = $this->makePaymentFlow($paymentLink);
        $parameters['payment_flow'] = $paymentFlow->identifier;
        $parameters['invoice_number'] = 'BOO-001';
        $parameters['tos_accepted'] = true;
        $previousInvoice = $result->getInvoice();
        $previousCustomer = $result->getCustomer();

        $result = $action->handleSubmit($paymentLink, $parameters);

        $this->assertNotEquals($previousInvoice->id(), $result->getInvoice()->id());
        $this->assertNotEquals($previousCustomer->id(), $result->getCustomer()->id());

        $this->assertEquals('test test', $result->getCustomer()->name);
        $this->assertEquals('company', $result->getCustomer()->type);
        $this->assertEquals('BOO-001', $result->getInvoice()->number);

        $payment = $result->getPayment();
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $payment->method);
        $this->assertEquals('eur', $payment->currency);
        $this->assertEquals(100, $payment->amount);

        $paymentFlow = $result->getPaymentFlow();
        $this->assertEquals('eur', $paymentFlow->currency);
        $this->assertEquals(100, $paymentFlow->amount);
        $this->assertEquals(PaymentFlowStatus::Succeeded, $paymentFlow->status);
    }

    public function testHandleSubmitExistingCustomer(): void
    {
        $action = $this->getAction();

        $customer = new Customer();
        $customer->name = 'Existing Customer';
        $customer->saveOrFail();
        $paymentLink = $this->makePaymentLink([
            'customer' => $customer,
        ]);
        $paymentFlow = $this->makePaymentFlow($paymentLink);

        $parameters = [
            'payment_flow' => $paymentFlow->identifier,
            'amount' => 100,
            'email' => 'test@test.com',
            'phone' => '5121234567',
            'address' => [
                'address1' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'NY',
                'postal_code' => '12345',
                'country' => 'US',
            ],
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];

        $result = $action->handleSubmit($paymentLink, $parameters);

        $this->assertEquals(PaymentLinkStatus::Completed, $paymentLink->status);
        $customer2 = Customer::findOrFail($result->getCustomer()->id); // test that changes are persisted to database
        $this->assertEquals($customer->id(), $customer2->id());
        $this->assertEquals('123 Main St', $customer2->address1);
        $this->assertEquals('Anytown', $customer2->city);
        $this->assertEquals('NY', $customer2->state);
        $this->assertEquals('12345', $customer2->postal_code);
        $this->assertEquals('US', $customer2->country);
        $this->assertEquals('5121234567', $customer2->phone);
        $this->assertEquals('test@test.com', $customer2->email);
    }

    public function testHandleSubmitSavedPaymentSource(): void
    {
        $action = $this->getAction();

        $paymentLink = $this->makePaymentLink([
            'customer' => self::$customer,
        ]);
        $paymentFlow = $this->makePaymentFlow($paymentLink);

        $parameters = [
            'payment_flow' => $paymentFlow->identifier,
            'amount' => 100,
            'email' => 'test@test.com',
            'phone' => '5121234567',
            'payment_source' => [
                'method' => 'saved:card:'.self::$card->id,
                'payment_source_type' => 'card',
                'payment_source_id' => self::$card->id,
            ],
        ];
        $result = $action->handleSubmit($paymentLink, $parameters);

        $invoice = $result->getInvoice();
        $this->assertEquals(100, $invoice->total);
        $this->assertEquals('usd', $invoice->currency);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('Payment Link', $invoice->items[0]->name);
        $this->assertEquals(self::$customer->id(), $invoice->customer);

        $payment = $result->getPayment();
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentMethod::CREDIT_CARD, $payment->method);
        $this->assertEquals('usd', $payment->currency);
        $this->assertEquals(100, $payment->amount);
        $this->assertEquals('test', $payment->charge?->gateway);
        $this->assertEquals('card', $payment->charge?->payment_source_type);
        $this->assertEquals(self::$card->id, $payment->charge?->payment_source_id);

        $paymentFlow = $result->getPaymentFlow();
        $this->assertEquals('usd', $paymentFlow->currency);
        $this->assertEquals(100, $paymentFlow->amount);
        $this->assertEquals(PaymentFlowStatus::Succeeded, $paymentFlow->status);
    }

    public function testHandleSubmitCustomField(): void
    {
        $action = $this->getAction();

        $paymentLink = $this->makePaymentLink();
        $paymentFlow = $this->makePaymentFlow($paymentLink);

        $this->makeCustomField(ObjectType::Customer, 'test');
        $this->makeCustomField(ObjectType::Invoice, 'test');
        $this->makePaymentLinkField($paymentLink, [
            'object_type' => ObjectType::Customer,
            'custom_field_id' => 'test',
        ]);
        $this->makePaymentLinkField($paymentLink, [
            'object_type' => ObjectType::Invoice,
            'custom_field_id' => 'test',
        ]);

        $parameters = [
            'payment_flow' => $paymentFlow->identifier,
            'amount' => 100,
            'company' => 'Acme Corp',
            'email' => 'test@test.com',
            'phone' => '5121234567',
            'customer__test' => '1234',
            'invoice__test' => 'hello world',
            'payment_source' => [
                'method' => PaymentMethod::CREDIT_CARD,
            ],
        ];
        $result = $action->handleSubmit($paymentLink, $parameters);

        $customer = $result->getCustomer();
        $this->assertEquals((object) ['test' => '1234'], $customer->metadata);

        $invoice = $result->getInvoice();
        $this->assertEquals((object) ['test' => 'hello world'], $invoice->metadata);
    }
}
