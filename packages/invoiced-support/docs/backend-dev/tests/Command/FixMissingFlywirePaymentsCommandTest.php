<?php

namespace App\Tests\Command;

use App\AccountsReceivable\Models\Invoice;
use App\EntryPoint\Command\FixMissingFlywirePaymentsCommand;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMissingFlywirePaymentsCommandTest extends AppTestCase
{
    public function testExecute(): void
    {
        $reconcile = Mockery::mock(PaymentFlowReconcile::class);
        $client = Mockery::mock(FlywirePrivateClient::class);

        $command = new class(self::getService('test.tenant'), $reconcile, $client) extends FixMissingFlywirePaymentsCommand {
            public function execute(InputInterface $input, OutputInterface $output): int
            {
                return parent::execute(
                    $input,
                    $output
                );
            }
        };

        self::hasCompany();
        self::hasMerchantAccount(FlywireGateway::ID, 'gateway_id_10'.time());
        self::hasCustomer();
        self::$customer->convenience_fee = true;
        self::$customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100.10,
            ],
        ];
        $invoice->saveOrFail();

        $transaction1 = new FlywirePayment();
        $transaction1->status = FlywirePaymentStatus::Processed;
        $transaction1->payment_id = 'payment1';
        $transaction1->recipient_id = 'recipient1';
        $transaction1->recipient_id = CarbonImmutable::now();
        $transaction1->amount_from = 100.20;
        $transaction1->amount_to = 100.10;
        $transaction1->currency_from = 'usd';
        $transaction1->currency_to = 'eur';
        $transaction1->reference = 'reference1';
        $transaction1->initiated_at = CarbonImmutable::now();
        $transaction1->merchant_account = self::$merchantAccount;
        $transaction1->saveOrFail();

        $transaction2 = new FlywirePayment();
        $transaction2->status = FlywirePaymentStatus::Delivered;
        $transaction2->payment_id = 'payment2';
        $transaction2->recipient_id = 'recipient2';
        $transaction2->recipient_id = CarbonImmutable::now();
        $transaction2->amount_from = 100.10;
        $transaction2->amount_to = 100.20;
        $transaction2->currency_from = 'usd';
        $transaction2->currency_to = 'eur';
        $transaction2->reference = 'reference2';
        $transaction2->initiated_at = CarbonImmutable::now();
        $transaction2->merchant_account = self::$merchantAccount;
        $transaction2->saveOrFail();

        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = '1234';
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 100.10;
        $paymentFlow->customer = self::$customer;
        $paymentFlow->initiated_from = PaymentFlowSource::CustomerPortal;
        $paymentFlow->gateway = AdyenGateway::ID;
        $paymentFlow->saveOrFail();

        $output = Mockery::mock(OutputInterface::class);
        $output->shouldReceive('writeln')
            ->with('Payment reconciliation dispatched for: '.$transaction2->id)
            ->times(2);

        $client->shouldReceive('getPayment')
            ->withSomeOfArgs($transaction2->payment_id)
            ->andReturn([
                'external_reference' => 'random',
                'callback' => [
                    'id' => 'random',
                    'url' => 'http://invoiced.localhost:1234/flywire/payment_callback/8',
                ],
            ])->once();

        $output->shouldReceive('writeln')
            ->with('Payment flow not found for reference: '.$transaction2->id.' - []')
            ->once();

        $command->execute(
            Mockery::mock(InputInterface::class),
            $output,
        );

        $client->shouldReceive('getPayment')
            ->withSomeOfArgs($transaction2->payment_id)
            ->andReturn([
                'id' => 'PTU0001',
                'status' => 'verification',
                'created_at' => '2022-10-11T12:00:00Z',
                'recipient' => [
                    'id' => '123456',
                    'fields' => [
                        [
                            'id' => 'invoiced_ref',
                            'value' => '1234',
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
                'callback' => [
                    'id' => '1234',
                    'url' => 'http://invoiced.localhost:1234/flywire/payment_callback/8',
                ],
            ]);

        $reconcile->shouldReceive('doReconcile')->once();
        $command->execute(
            Mockery::mock(InputInterface::class),
            $output,
        );

        $transaction2->refresh();
        $this->assertEquals('1234', $transaction2->reference);
    }
}
