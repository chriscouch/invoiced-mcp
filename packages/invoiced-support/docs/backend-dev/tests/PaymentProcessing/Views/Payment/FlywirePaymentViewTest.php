<?php

namespace App\Tests\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;

class FlywirePaymentViewTest extends PaymentViewTestBase
{
    const METHOD = PaymentMethod::CREDIT_CARD;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::acceptsFlywire();
        self::$company->features->enable('flywire_surcharging');
        self::$customer->surcharging = true;
    }

    public function testShouldBeShown(): void
    {
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $view = $this->getView($method);
        $form = $this->getFormBuilder()->build();

        $this->assertFalse($view->shouldBeShown($form, $method, self::$merchantAccount));

        self::$merchantAccount->credentials = (object) ['flywire_portal_codes' => [['currency' => 'usd', 'id' => '1234']]];
        self::$merchantAccount->saveOrFail();
        $this->assertFalse($view->shouldBeShown($form, $method, self::$merchantAccount));

        self::$merchantAccount->credentials = (object) ['flywire_portal_codes' => [['currency' => 'usd', 'id' => '1234']], 'shared_secret' => 'test'];
        self::$merchantAccount->saveOrFail();
        $this->assertTrue($view->shouldBeShown($form, $method, self::$merchantAccount));
    }

    public function testGetPaymentFormCapabilities(): void
    {
        $method = PaymentMethod::instance(self::$company, static::METHOD);
        $view = $this->getView($method);
        $this->assertEquals(new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: false,
            hasReceiptEmail: false,
        ), $view->getPaymentFormCapabilities());
    }

    protected function cleanViewParameters(array $params): array
    {
        $params = parent::cleanViewParameters($params);
        unset($params['jsonId']);
        unset($params['paymentMethodValues']['config']['nonce']);
        unset($params['paymentMethodValues']['config']['additionalData']['items'][0]['product_code']);

        return $params;
    }

    protected function expectedViewParameters(PaymentFormBuilder $form, PaymentMethod $paymentMethod): array
    {
        return array_replace([
            'paymentMethodValues' => [
                'paymentMethod' => 'credit_card',
                'type' => 'flywire_payment',
                'config' => [
                    'environment' => 'demo',
                    'code' => '1234',
                    'amount' => 100.0,
                    'address' => 'Test, Address',
                    'state' => 'TX',
                    'callbackUrl' => 'http://invoiced.localhost:1234/flywire/payment_callback/'.self::$merchantAccount->id,
                    'callbackId' => self::$paymentFlow->identifier,
                    'email' => 'sherlock@example.com',
                    'phone' => null,
                    'firstName' => 'Bob',
                    'lastName' => 'Loblaw',
                    'city' => 'Austin',
                    'zip' => '78701',
                    'country' => 'US',
                    'convenienceFee' => false,
                    'surcharging' => true,
                    'alert' => 'Are you sure you want to close this? If you have initiated a payment then please wait for the redirect to complete.',
                    'locale' => 'en',
                    'sort' => [
                        ['type' => ['credit_card', 'bank_transfer', 'direct_debit', 'online']],
                    ],
                    'filters' => [
                        'type' => ['credit_card'],
                    ],
                    'customer' => [
                        'metadata' => self::$customer->metadata,
                        'name' => self::$customer->name,
                        'number' => self::$customer->number,
                    ],
                    'documents' => [
                        [
                            'metadata' => new \stdClass(),
                            'name' => 'Invoice',
                            'number' => 'INV-00001',
                            'type' => 'invoice',
                        ],
                    ],
                    'additionalData' => [
                        'customer_reference' => 'ABC',
                        'duty_amount' => 0,
                        'shipping_amount' => 0,
                        'total_tax_amount' => 1000,
                        'total_discount_amount' => 0,
                        'card_acceptor_tax_id' => null,
                        'items' => [
                            [
                                'description' => 'Test Item',
                                'quantity' => 1,
                                'unit_of_measure' => 'EA',
                                'tax_amount' => 1000,
                                'discount_amount' => 0,
                                'unit_price' => 9000,
                                'total_amount' => 9000,
                                'total_amount_with_tax' => 10000
                            ]
                        ]
                    ],
                ],
            ],
        ]);
    }
}
