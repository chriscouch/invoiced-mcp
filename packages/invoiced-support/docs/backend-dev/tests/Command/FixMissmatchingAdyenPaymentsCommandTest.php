<?php

namespace App\Tests\Command;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\EntryPoint\Command\FixMismatchingAdyenPaymentsCommand;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMissmatchingAdyenPaymentsCommandTest extends AppTestCase
{
    public function testExecute(): void
    {
        $command = new class(self::getService('test.database'), self::getService('test.tenant')) extends FixMismatchingAdyenPaymentsCommand {
            public function execute(InputInterface $input, OutputInterface $output): int
            {
                return parent::execute(
                    $input,
                    $output
                );
            }
        };

        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID, 'gateway_id_9'.time());
        self::hasCustomer();
        self::$customer->convenience_fee = true;
        self::$customer->saveOrFail();
        $paymentMethod = self::getTestDataFactory()->acceptsPaymentMethod(self::$company, PaymentMethod::CREDIT_CARD, AdyenGateway::ID);
        $paymentMethod->convenience_fee = 400;
        $paymentMethod->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 200,
            ],
        ];
        $invoice->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 200,
            ],
        ];
        $invoice2->saveOrFail();

        // no fee
        $transaction1 = new MerchantAccountTransaction();
        $transaction1->merchant_account = self::$merchantAccount;
        $transaction1->reference = RandomString::generate();
        $transaction1->type = MerchantAccountTransactionType::Payment;
        $transaction1->currency = 'usd';
        $transaction1->amount = 10;
        $transaction1->fee = 0;
        $transaction1->net = 10;
        $transaction1->description = 'Payment';
        $transaction1->available_on = CarbonImmutable::now();
        $transaction1->saveOrFail();

        $fee = ConvenienceFeeHelper::calculate($paymentMethod, self::$customer, Money::fromDecimal('usd', 50));

        // fee match
        $transaction2 = new MerchantAccountTransaction();
        $transaction2->merchant_account = self::$merchantAccount;
        $transaction2->reference = RandomString::generate();
        $transaction2->type = MerchantAccountTransactionType::Payment;
        $transaction2->currency = 'usd';
        $transaction2->amount = $fee['total']->toDecimal();
        $transaction2->fee = $fee['amount']->toDecimal();
        $transaction2->net = 50;
        $transaction2->description = 'Payment';
        $transaction2->available_on = CarbonImmutable::now();
        $transaction2->saveOrFail();

        // fee mismatch
        $transaction3 = new MerchantAccountTransaction();
        $transaction3->merchant_account = self::$merchantAccount;
        $transaction3->reference = RandomString::generate();
        $transaction3->type = MerchantAccountTransactionType::Payment;
        $transaction3->currency = 'usd';
        $transaction3->amount = $fee['total']->toDecimal() + 1;
        $transaction3->fee = $fee['amount']->toDecimal() + 1;
        $transaction3->net = 50;
        $transaction3->description = 'Payment';
        $transaction3->available_on = CarbonImmutable::now();
        $transaction3->saveOrFail();

        // fee applied
        $transaction4 = new MerchantAccountTransaction();
        $transaction4->merchant_account = self::$merchantAccount;
        $transaction4->reference = RandomString::generate();
        $transaction4->type = MerchantAccountTransactionType::Payment;
        $transaction4->currency = 'usd';
        $transaction4->amount = 20;
        $transaction4->fee = 10;
        $transaction4->net = 10;
        $transaction4->description = 'Payment';
        $transaction4->available_on = CarbonImmutable::now();
        $transaction4->saveOrFail();

        // no fee
        $payment1 = $this->makePayment(
            11,
            $transaction1,
            [
                [
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoice2,
                    'amount' => 10,
                ],
            ]
        );

        // fee match
        $payment2 = $this->makePayment(50,
            $transaction2,
            [
                [
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoice2,
                    'amount' => 25,
                ],
                [
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoice,
                    'amount' => 25,
                ],
            ]
        );

        // fee nissmatch
        $payment3 = $this->makePayment(50,
            $transaction3,
            [
                [
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoice2,
                    'amount' => 25,
                ],
                [
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoice,
                    'amount' => 25,
                ],
            ]
        );

        // fee applied
        $payment4 = $this->makePayment(21,
            $transaction4,
            [
                [
                    'type' => PaymentItemType::Invoice->value,
                    'invoice' => $invoice2,
                    'amount' => 10,
                ],
                [
                    'type' => 'convenience_fee',
                    'amount' => 10,
                ],
            ]
        );

        $output = Mockery::mock(OutputInterface::class);

        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Payment has convenience fee transaction: '.$payment4->payment?->id)
            ->twice();
        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Payment amount missmatch: '.$payment1->payment?->id.' - 0.44 - 1.0000000000')
            ->twice();
        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Payment amount missmatch: '.$payment3->payment?->id.' - 2 - -3.0000000000')
            ->twice();
        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Processing payment: '.$payment1->payment?->id.' - 1.0000000000')
            ->twice();
        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Processing payment: '.$payment2->payment?->id.' - -2.0000000000')
            ->twice();
        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Processing payment: '.$payment3->payment?->id.' - -3.0000000000')
            ->twice();
        $output->shouldReceive('writeln')
            ->withSomeOfArgs('Processing payment: '.$payment4->payment?->id.' - 1.0000000000')
            ->twice();

        $command->execute(
            Mockery::mock(InputInterface::class),
            $output,
        );

        $payment1->refresh();
        $this->assertEquals(11, $payment1->payment?->amount);
        $this->assertEquals(11, $payment1->amount);
        $this->assertCount(1, $payment1->payment?->getTransactions() ?? []);

        $payment2->refresh();
        $this->assertEquals(52, $payment2->payment?->amount);
        $this->assertEquals(52, $payment2->amount);
        $transactions = $payment2->payment?->getTransactions() ?? [];
        $this->assertCount(3, $transactions);
        $transaction = $transactions[2] ?? null;
        $this->assertTrue($transaction?->isConvenienceFee());

        $payment3->refresh();
        $this->assertEquals(50, $payment3->payment?->amount);
        $this->assertEquals(50, $payment3->amount);
        $this->assertCount(2, $payment3->payment?->getTransactions() ?? []);

        $payment4->refresh();
        $this->assertEquals(21, $payment4->payment?->amount);
        $this->assertEquals(21, $payment4->amount);
        $this->assertCount(2, $payment4->payment?->getTransactions() ?? []);

        // test secondary money application, in case first one was unsuccessful
        $payment2->amount = 50;
        $payment2->saveOrFail();

        $command->execute(
            Mockery::mock(InputInterface::class),
            $output,
        );

        $payment2->refresh();
        $this->assertEquals(52, $payment2->payment?->amount);
        $this->assertEquals(52, $payment2->amount);
        $transactions = $payment2->payment?->getTransactions() ?? [];
        $this->assertCount(3, $transactions);
        $transaction = $transactions[2] ?? null;
        $this->assertTrue($transaction?->isConvenienceFee());
    }

    private function makePayment(
        int $amount,
        MerchantAccountTransaction $transaction,
        array $appliedTo = []
    ): Charge {
        // no fee
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->setCustomer(self::$customer);
        $payment->amount = $amount;
        $payment->applied_to = $appliedTo;
        $payment->method = 'credit_card';
        $payment->saveOrFail();

        $charge = new Charge();
        $charge->payment = $payment;
        $charge->currency = $payment->currency;
        $charge->amount = $payment->amount;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = RandomString::generate(22);
        $charge->merchant_account_transaction = $transaction;
        $charge->saveOrFail();

        return $charge;
    }
}
