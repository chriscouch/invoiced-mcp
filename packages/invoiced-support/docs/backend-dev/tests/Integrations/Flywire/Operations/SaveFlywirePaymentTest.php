<?php

namespace App\Tests\Integrations\Flywire\Operations;

use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Models\Payment;
use App\Core\Utils\RandomString;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Operations\SaveFlywirePayment;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\Tests\AppTestCase;
use Mockery;

class SaveFlywirePaymentTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount(FlywireGateway::ID, 'gateway3_'.time());
        self::hasCustomer();
        self::hasInvoice();
    }

    private function performOperation(array $input): void
    {
        $client = Mockery::mock(FlywirePrivateClient::class);
        $updateChargeStatus = self::getService('test.update_charge_status');
        $client->shouldReceive('getPayment')->andReturn($input)->once();
        $operation = new SaveFlywirePayment($client, $updateChargeStatus, self::getService('test.payment_flow_reconcile'));
        $operation->sync($input['id'], self::$merchantAccount);
    }

    public function testIgnorePayment(): void
    {
        $idintifier = RandomString::generate(10);
        $input = [
            'id' => 'PTU0001',
            'status' => 'verification',
            'created_at' => '2022-10-11T12:00:00Z',
            'recipient' => [
                'id' => '123456',
                'fields' => [
                    [
                        'id' => 'invoiced_ref',
                        'value' => $idintifier,
                    ],
                ],
            ],
            'charge_events' => [],
            'purchase' => [
                'value' => 1501,
                'currency' => [
                    'code' => 'USD',
                ],
            ],
            'price' => [
                'value' => 1601,
                'currency' => [
                    'code' => 'USD',
                ],
            ],
        ];

        $this->performOperation($input);
        $this->assertEquals(0, Payment::where('reference', 'PTU0001')->count());
        $this->assertEquals(1, FlywirePayment::where('payment_id', 'PTU0001')->count());

        $input['status'] = 'delivered';
        $this->performOperation($input);
        $this->assertEquals(0, Payment::where('reference', 'PTU0001')->count());
        $this->assertEquals(1, FlywirePayment::where('payment_id', 'PTU0001')->count());

        $input['offer'] = [
            'type' => 'rendom',
        ];
        $this->performOperation($input);
        $this->assertEquals(0, Payment::where('reference', 'PTU0001')->count());
        $this->assertEquals(1, FlywirePayment::where('payment_id', 'PTU0001')->count());

        // payment only created if payment flow is present
        $input['offer'] = [
            'type' => 'bank_transfer',
        ];
        $this->assertEquals(0, Payment::where('reference', 'PTU0001')->count());
        $this->assertEquals(1, FlywirePayment::where('payment_id', 'PTU0001')->count());

        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = $idintifier;
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->initiated_from = PaymentFlowSource::Api;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 123;
        $paymentFlow->customer = self::$customer;
        $paymentFlow->saveOrFail();

        $item1 = new PaymentFlowApplication();
        $item1->payment_flow = $paymentFlow;
        $item1->type = PaymentItemIntType::Invoice;
        $item1->amount = 15.01;
        $item1->invoice = self::$invoice;
        $item1->saveOrFail();

        $this->performOperation($input);
        /** @var Payment[] $payments */
        $payments = Payment::first();
        $this->assertCount(1, $payments);
        $this->assertEquals(1, FlywirePayment::where('payment_id', 'PTU0001')->count());
        $this->assertEquals(15.01, $payments[0]->amount);

        // has payment
        FlywirePayment::where('payment_id', 'PTU0001')->delete();
        $this->performOperation($input);
        $this->assertEquals(1, Payment::where('reference', 'PTU0001')->count());
        /** @var FlywirePayment $payment */
        $payment = FlywirePayment::where('payment_id', 'PTU0001')->one();
        $this->assertEquals($payment->ar_payment?->reference, 'PTU0001');
    }
}
