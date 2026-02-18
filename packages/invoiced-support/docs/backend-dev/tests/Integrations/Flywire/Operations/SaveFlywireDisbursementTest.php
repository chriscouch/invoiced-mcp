<?php

namespace App\Tests\Integrations\Flywire\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireDisbursement;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Models\FlywirePayout;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Integrations\Flywire\Operations\SaveFlywireDisbursement;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class SaveFlywireDisbursementTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::getService('test.database')->executeStatement('
        DELETE FROM FlywirePayouts;
        DELETE FROM FlywireRefunds;
        DELETE FROM FlywirePayments;
        DELETE FROM FlywireDisbursements;
        ');
        self::hasMerchantAccount('flywire', 'XXX');

        $disbursementSynced = new FlywireDisbursement();
        $disbursementSynced->disbursement_id = 'DISBURSEMENT_SYNCED';
        $disbursementSynced->status_text = 'random';
        $disbursementSynced->recipient_id = 'ZZZ';
        $disbursementSynced->delivered_at = CarbonImmutable::now();
        $disbursementSynced->bank_account_number = 'test';
        $disbursementSynced->setAmount(new Money('USD', 1));
        $disbursementSynced->saveOrFail();

        $disbursementNotSynced = new FlywireDisbursement();
        $disbursementNotSynced->disbursement_id = 'DISBURSEMENT_NOT_SYNCED';
        $disbursementNotSynced->status_text = 'random';
        $disbursementNotSynced->recipient_id = 'ZZZ';
        $disbursementNotSynced->delivered_at = CarbonImmutable::now();
        $disbursementNotSynced->bank_account_number = 'test';
        $disbursementNotSynced->setAmount(new Money('USD', 1));
        $disbursementNotSynced->saveOrFail();

        $payment = new FlywirePayment();
        $payment->payment_id = 'PAY_EX_1';
        $payment->setAmountFrom(new Money('USD', 2000));
        $payment->setAmountTo(new Money('UAH', 2000));
        $payment->status = FlywirePaymentStatus::Failed;
        $payment->cancellation_reason = 'test';
        $payment->reason = 'test';
        $payment->reason_code = 'test';
        $payment->merchant_account = self::$merchantAccount;
        $payment->initiated_at = CarbonImmutable::now();
        $payment->recipient_id = 'recipient_id1';
        $payment->saveOrFail();

        $payment2 = new FlywirePayment();
        $payment2->payment_id = 'PAY_EX_2';
        $payment2->setAmountFrom(new Money('USD', 2000));
        $payment2->setAmountTo(new Money('UAH', 2000));
        $payment2->status = FlywirePaymentStatus::Failed;
        $payment2->cancellation_reason = 'test';
        $payment2->reason = 'test';
        $payment2->reason_code = 'test';
        $payment2->merchant_account = self::$merchantAccount;
        $payment2->initiated_at = CarbonImmutable::now();
        $payment2->recipient_id = 'recipient_id2';
        $payment2->saveOrFail();

        $payout = new FlywirePayout();
        $payout->payout_id = 'PAYOUT_EXISTENT';
        $payout->payment = $payment;
        $payout->status_text = 'random';
        $payout->setAmount(new Money('USD', 1));
        $payout->disbursement = $disbursementNotSynced;
        $payout->saveOrFail();

        $refund = new FlywireRefund();
        $refund->refund_id = 'EX_REF_1';
        $refund->payment = $payment;
        $refund->status = FlywireRefundStatus::Initiated;
        $refund->setAmount(new Money('USD', 1));
        $refund->setAmountTo(new Money('USD', 1));
        $refund->disbursement = $disbursementNotSynced;
        $refund->initiated_at = CarbonImmutable::now();
        $refund->recipient_id = 'recipient_id1';
        $refund->saveOrFail();

        $refund = new FlywireRefund();
        $refund->refund_id = 'EX_REF_2';
        $refund->status = FlywireRefundStatus::Initiated;
        $refund->setAmount(new Money('USD', 1));
        $refund->setAmountTo(new Money('USD', 1));
        $refund->recipient_id = 'recipient_id1';
        $refund->initiated_at = CarbonImmutable::now();
        $refund->saveOrFail();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSync(
        array $input,
        array $expected,
        array $payouts,
        array $payment,
        array $refunds,
        array $expectedRefunds,
    ): void {
        $privateClient = Mockery::mock(FlywirePrivateClient::class);

        $privateClient->shouldReceive('getDisbursementPayouts')
            ->andReturn((fn () => yield from $payouts)())
            ->times($payouts ? 1 : 0);

        $privateClient->shouldReceive('getDisbursementRefunds')
            ->andReturn((fn () => yield from $refunds)())
            ->times($refunds ? 1 : 0);

        $disbursementProvider = new SaveFlywireDisbursement(
            $privateClient,
        );
        $disbursementProvider->setStatsd(new StatsdClient());

        $disbursementProvider->sync($input);

        $disbursement = FlywireDisbursement::where('disbursement_id', $input['reference'])->one();
        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $disbursement->$key);
        }

        foreach ($payouts as $payout) {
            // we skip payment which has not been created
            if ('PAY_NEW' === $payout['payment_id']) {
                continue;
            }
            /** @var FlywirePayout $payoutSave */
            $payoutSave = FlywirePayout::where('payout_id', $payout['reference'])->one();
            $this->assertEquals($payout['status'], $payoutSave->status_text);
            $this->assertEquals($payout['disbursement_id'], $payoutSave->disbursement->disbursement_id);
            $this->assertEquals($payout['payment_id'], $payoutSave->payment->payment_id);
            $this->assertEquals($payout['amount'] / 100, $payoutSave->amount);
            $this->assertEquals(strtolower($payout['currency']['code']), $payoutSave->currency);
        }

        foreach ($expectedRefunds as $refund) {
            /** @var FlywireRefund $refundSave */
            $refundSave = FlywireRefund::where('refund_id', $refund['refund_id'])->one();
            $this->assertEquals(FlywireRefundStatus::fromString($refund['status']), $refundSave->status);
            $this->assertEquals($refund['recipient_id'], $refundSave->recipient_id);
            $this->assertEquals(new Money($refund['currency'], $refund['amount']), $refundSave->getAmount());
            $this->assertEquals(new Money($refund['currency_to'], $refund['amount_to']), $refundSave->getAmountTo());
        }
    }

    public function dataProvider(): array
    {
        return [
            [
                [
                    'reference' => 'DISBURSEMENT_SYNCED',
                    'status' => 'delivered',
                    'number_of_payments' => 0,
                    'number_of_refunds' => 0,
                    'destination_code' => 'XXX',
                    'delivered_at' => '2016-04-14T14:32:29Z',
                    'bank_account_number' => 'XXXXX3682',
                    'amount' => [
                        'value' => 123000,
                        'currency' => [
                            'code' => 'EUR',
                        ],
                    ],
                ],
                [
                    'disbursement_id' => 'DISBURSEMENT_SYNCED',
                    'status_text' => 'delivered',
                    'recipient_id' => 'XXX',
                    'delivered_at' => new CarbonImmutable('2016-04-14 14:32:29'),
                    'bank_account_number' => 'XXXXX3682',
                    'amount' => 1230,
                    'currency' => 'eur',
                ],
                [],
                [],
                [],
                [],
            ],
            [
                [
                    'reference' => 'DISBURSEMENT_NOT_SYNCED',
                    'status' => 'delivered',
                    'number_of_payments' => 2,
                    'number_of_refunds' => 3,
                    'destination_code' => 'XXX',
                    'delivered_at' => '2016-04-14T14:32:29Z',
                    'bank_account_number' => 'XXXXX3682',
                    'amount' => [
                        'value' => 123000,
                        'currency' => [
                            'code' => 'EUR',
                        ],
                    ],
                ],
                [
                    'disbursement_id' => 'DISBURSEMENT_NOT_SYNCED',
                    'status_text' => 'delivered',
                    'recipient_id' => 'XXX',
                    'delivered_at' => new CarbonImmutable('2016-04-14 14:32:29'),
                    'bank_account_number' => 'XXXXX3682',
                    'amount' => 1230,
                    'currency' => 'eur',
                ],
                [
                    [
                        'recipient_id' => 'PAY',
                        'reference' => 'PAYOUT_EXISTENT',
                        'disbursement_id' => 'DISBURSEMENT_NOT_SYNCED',
                        'status' => 'notified',
                        'payment_id' => 'PAY_EX_1',
                        'amount' => 2000,
                        'currency' => [
                            'code' => 'EUR',
                            'name' => 'Euro',
                            'decimal_mark' => ',',
                            'plural_name' => 'Euros',
                            'subunit_to_unit' => 100,
                            'symbol' => 'â¬',
                            'symbol_first' => false,
                            'thousands_separator' => '.',
                            'units_to_round' => 1,
                        ],
                    ],
                    [
                        'recipient_id' => 'PAY',
                        'reference' => 'PAYOUT_NON_EXISTENT',
                        'disbursement_id' => 'DISBURSEMENT_NOT_SYNCED',
                        'status' => 'notified',
                        'payment_id' => 'PAY_EX_2',
                        'amount' => 2000,
                        'currency' => [
                            'code' => 'EUR',
                            'name' => 'Euro',
                            'decimal_mark' => ',',
                            'plural_name' => 'Euros',
                            'subunit_to_unit' => 100,
                            'symbol' => 'â¬',
                            'symbol_first' => false,
                            'thousands_separator' => '.',
                            'units_to_round' => 1,
                        ],
                    ],
                ],
                [
                    'payment_id' => 'PAY_NEW',
                    'recipient' => ['id' => 'recipient_id1'],
                    'created_at' => '2021-10-01T00:00:00Z',
                    'amount_from' => 20,
                    'amount_to' => 20,
                    'currency_from' => 'eur',
                    'currency_to' => 'eur',
                    'status' => 'failed',
                    'expiration_date' => null,
                    'payment_method_details' => [
                        'type' => 'card',
                        'brand' => 'visa',
                        'card_classification' => 'credit',
                        'card_expiration' => '2023-10',
                        'last_four_digits' => '1234',
                        'reason' => [
                            'description' => 'test',
                            'code' => 'test',
                        ],
                    ],
                    'cancellation_reason' => 'test',
                ],
                [
                    [
                        'id' => 'EX_REF_1',
                        'payment' => [
                            'id' => 'PAY_EX_1',
                        ],
                        'bundle' => [
                            'id' => 'BUNDLE_1',
                        ],
                        'amount' => [
                            'value' => '100',
                            'currency' => [
                                'code' => 'USD',
                            ],
                        ],
                    ],
                    [
                        'id' => 'EX_REF_2',
                        'payment' => [
                            'id' => 'PAY_EX_2',
                        ],
                        'bundle' => [
                            'id' => 'BUNDLE_1',
                        ],
                        'amount' => [
                            'value' => '100',
                            'currency' => [
                                'code' => 'USD',
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'refund_id' => 'EX_REF_2',
                        'recipient_id' => 'recipient_id1',
                        'created_at' => '2021-10-01T00:00:00Z',
                        'amount' => 1,
                        'currency' => 'USD',
                        'amount_to' => 1,
                        'currency_to' => 'USD',
                        'status' => 'initiated',
                        'payment_id' => 'PAY_EX_2',
                    ],
                ],
            ],
            [
                [
                    'reference' => 'DISBURSEMENT_NEW',
                    'status' => 'delivered',
                    'number_of_payments' => 1,
                    'number_of_refunds' => 0,
                    'destination_code' => 'YYY',
                    'delivered_at' => '2016-04-14T14:32:29Z',
                    'bank_account_number' => 'XXXXX3682',
                    'amount' => [
                        'value' => 123000,
                        'currency' => [
                            'code' => 'EUR',
                        ],
                    ],
                ],
                [
                    'disbursement_id' => 'DISBURSEMENT_NEW',
                    'status_text' => 'delivered',
                    'recipient_id' => 'YYY',
                    'delivered_at' => new CarbonImmutable('2016-04-14 14:32:29'),
                    'bank_account_number' => 'XXXXX3682',
                    'amount' => 1230,
                    'currency' => 'eur',
                ],
                [
                    [
                        'recipient_id' => 'PAY',
                        'reference' => 'NEWPAYMENT744586810',
                        'disbursement_id' => 'DISBURSEMENT_NEW',
                        'status' => 'notified',
                        'payment_id' => 'PAY_NEW',
                        'amount' => 2000,
                        'currency' => [
                            'code' => 'EUR',
                            'name' => 'Euro',
                            'decimal_mark' => ',',
                            'plural_name' => 'Euros',
                            'subunit_to_unit' => 100,
                            'symbol' => 'â¬',
                            'symbol_first' => false,
                            'thousands_separator' => '.',
                            'units_to_round' => 1,
                        ],
                    ],
                ],
                [],
                [],
                [],
            ],
        ];
    }
}
