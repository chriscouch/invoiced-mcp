<?php

namespace App\Tests\Integrations\Adyen\ReportHandler;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemIntType;
use App\Core\Mailer\Mailer;
use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\Integrations\Adyen\ReportHandler\Accounting\ChargebackGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\ChargebackReversalGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\InternalTransferGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\PaymentGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\PaymentReversalGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\PayoutGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\RefundGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\RefundReversalGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\TopUpGroupHandler;
use App\Integrations\Adyen\ReportHandler\AccountingReportHandler;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\Reconciliation\DisputeReconciler;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Mockery;
use Money\Currency;
use Money\Money;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\Operations\VoidRefund;

class AccountingReportHandlerTest extends AbstractReportHandlerTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->executeStatement('TRUNCATE TABLE  AdyenPaymentResults');
        $connection->executeStatement('DELETE FROM  PaymentFlows where identifier = "o7oc00hz2vumlf3qzo9l2ubq1qhcwliq"');
    }

    public function testHandleRow(): void
    {
        $merchantAccount1 = $this->createMerchantAccount('3839-sjwpjeuzlj');
        $merchantAccount2 = $this->createMerchantAccount('18251-fjwzzmskqa');
        $merchantAccount3 = $this->createMerchantAccount('5438104-uuaaqjisbb');

        $charge1 = $this->makeCharge('D5MZ3M7F6S2SKWX3', 405.6);
        $charge2 = $this->makeCharge('LFD33DRJWRQ983Z3', 4000);
        $charge3 = $this->makeCharge('XRRC2N87N4625TG3', 1);
        $chargeRefundReversal = $this->makeCharge('W9QRGQXBVFKR6QZ3', 690.0, 'card',690.0);
        $refund3 = $this->makeRefund('W9QRGQXBVFKR6QZ3', $chargeRefundReversal, 690.0);

        /** @var PaymentFlowManager $manager */
        $manager = self::getService('test.payment_flow_manager');

        // existing payment, should not be created
        $manager->saveResult('o7oc00hz2vumlf3qzo9l2ubq1qhcwliq', [
            'pspReference' => 'D5MZ3M7F6S2SKWX3',
        ]);
        // new charge, should be created
        $manager->saveResult('o7oc00hz2vumlf3qzo9l2ubq1qhcwliq', [
            'pspReference' => 'H55FDJM7G98CQZW3',
            'resultCode' => 'Authorised',
            'paymentMethod' => [
                'brand' => 'Visa',
            ],
            'amount' => [
                'currency' => 'usd',
                'value' => 350000,
            ],
        ]);
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 3500,
            ],
        ];
        $invoice->saveOrFail();
        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = 'o7oc00hz2vumlf3qzo9l2ubq1qhcwliq';
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->initiated_from = PaymentFlowSource::Api;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 3500;
        $paymentFlow->customer = self::$customer;
        $paymentFlow->merchant_account = $merchantAccount2;
        $paymentFlow->gateway = AdyenGateway::ID;
        $paymentFlow->saveOrFail();
        $item1 = new PaymentFlowApplication();
        $item1->payment_flow = $paymentFlow;
        $item1->type = PaymentItemIntType::Invoice;
        $item1->amount = 3500;
        $item1->invoice = $invoice;
        $item1->saveOrFail();


        //Flow to be reconciled without result INV-255
        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = '97szusfv9t4a8qdti7t7rr0p5osmwn69';
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->initiated_from = PaymentFlowSource::Api;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 500;
        $paymentFlow->customer = self::$customer;
        $paymentFlow->merchant_account = $merchantAccount3;
        $paymentFlow->gateway = AdyenGateway::ID;
        $paymentFlow->saveOrFail();
        $item1 = new PaymentFlowApplication();
        $item1->payment_flow = $paymentFlow;
        $item1->type = PaymentItemIntType::Invoice;
        $item1->amount = 500;
        $item1->invoice = $invoice;
        $item1->saveOrFail();

        // Process all the reports
        $this->handleFile('balanceplatform_accounting_report_2025_02_17.csv');
        $this->handleFile('balanceplatform_accounting_report_2025_02_19.csv');
        $this->handleFile('balanceplatform_accounting_report_2025_03_02.csv');
        $this->handleFile('balanceplatform_accounting_report_2025_03_03.csv');
        //Top Up
        $this->handleFile('balanceplatform_accounting_report_2025_05_15.csv');
        //INV-229 mixed up fee/amount
        $this->handleFile('balanceplatform_accounting_report_2025_05_05.csv');
        //INV-269 capture reversal
        $this->handleFile('balanceplatform_accounting_report_2025_05_22.csv');
        //payment partially reflected in the report
        $this->handleFile('balanceplatform_accounting_report_2025_06_27.csv');
        //failed report on positive refund
        $this->handleFile('balanceplatform_accounting_report_2025_07_05.csv');
        //INV-397 Acquiring fee reversals
        $this->handleFile('balanceplatform_accounting_report_2025_07_18.csv');
        //INV-429 Manual corrections
        $this->handleFile('balanceplatform_accounting_report_2025_08_18.csv');
        //INV-489 Refund Reversal
        $this->handleFile('balanceplatform_accounting_report_2025_09_18.csv');

        // Validate the transactions that were created
        $charge1->refresh();
        $transaction1 = $charge1->merchant_account_transaction;
        $this->checkTransaction($transaction1, $merchantAccount1, [
            'amount' => 405.6,
            'available_on' => new CarbonImmutable('2025-02-19'),
            'currency' => 'usd',
            'description' => 'Payment',
            'fee' => 11.76,
            'fee_details' => [
                [
                    'amount' => 11.76,
                    'type' => 'flywire_fee',
                ],
            ],
            'net' => 393.84,
            'payout_id' => null,
            'reference' => 'D5MZ3M7F6S2SKWX3',
            'source_id' => $charge1->id,
            'source_type' => 'charge',
            'type' => 'payment',
            'merchant_reference' => '8b47ckl2qu8y3hw94hml88j1whpjsh8y',
        ]);

        $charge2->refresh();
        $transaction2 = $charge2->merchant_account_transaction;
        $this->checkTransaction($transaction2, $merchantAccount2, [
            'amount' => 4000.0,
            'available_on' => new CarbonImmutable('2025-02-19'),
            'currency' => 'usd',
            'description' => 'Payment',
            'fee' => 116,
            'fee_details' => [
                [
                    'amount' => 116,
                    'type' => 'flywire_fee',
                ],
            ],
            'net' => 3884,
            'payout_id' => null,
            'reference' => 'LFD33DRJWRQ983Z3',
            'source_id' => $charge2->id,
            'source_type' => 'charge',
            'type' => 'payment',
            'merchant_reference' => 'v2nyqx6qxu68s1gzqc1rqghsbi0654eu',
        ]);

        $charge3 = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', 'H55FDJM7G98CQZW3')
            ->one();
        $this->assertEquals($charge3->amount, 3500);
        $transaction3 = $charge3->merchant_account_transaction;
        $this->checkTransaction($transaction3, $merchantAccount2, [
            'amount' => 3500,
            'available_on' => new CarbonImmutable('2025-03-05'),
            'currency' => 'usd',
            'description' => 'INV-00001',
            'fee' => 101.5,
            'fee_details' => [
                [
                    'amount' => 101.5,
                    'type' => 'flywire_fee',
                ],
            ],
            'net' => 3398.5,
            'payout_id' => null,
            'reference' => 'H55FDJM7G98CQZW3',
            'source_id' => $charge3->id,
            'source_type' => 'charge',
            'type' => 'payment',
            'merchant_reference' => 'o7oc00hz2vumlf3qzo9l2ubq1qhcwliq',
        ]);

        $transaction4 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'B45L2QDRCGSNPVX3')
            ->oneOrNull();
        $this->checkTransaction($transaction4, $merchantAccount3, [
            'amount' => 2.0,
            'available_on' => new DateTimeImmutable('2025-03-05'),
            'currency' => 'usd',
            'description' => 'Payment',
            'fee' => 0.19,
            'fee_details' => [
                [
                    'amount' => .01,
                    'type' => 'flywire_fee',
                ],
                [
                    'amount' => .03,
                    'type' => 'scheme_fees',
                ],
                [
                    'amount' => .15,
                    'type' => 'interchange',
                ],
            ],
            'net' => 1.81,
            'payout_id' => null,
            'reference' => 'B45L2QDRCGSNPVX3',
            'source_id' => null,
            'source_type' => null,
            'type' => 'payment',
            'merchant_reference' => 'ymryi46wnx4uq8v0vegboi5sx29s3q5f',
        ]);

        $transaction4 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'WR93WQ4QQFLHMTX3')
            ->oneOrNull();
        $this->checkTransaction($transaction4, $merchantAccount3, [
            'amount' => -18002.34,
            'available_on' => new DateTimeImmutable('2025-05-16'),
            'currency' => 'usd',
            'description' => 'Top Up',
            'fee' => 0,
            'fee_details' => [],
            'net' => -18002.34,
            'payout_id' => null,
            'reference' => 'WR93WQ4QQFLHMTX3',
            'source_id' => null,
            'source_type' => null,
            'type' => 'topup',
            'merchant_reference' => 'zqecotnvcxbdkmcnhwduhuqclweurkam',
        ]);

        $paymentReversalTransaction = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'QV97RD3J3QG7J424')
            ->one();
        $this->checkTransaction($paymentReversalTransaction, $merchantAccount3, [
            'amount' => -16515.58,
            'available_on' => new DateTimeImmutable('2025-05-23'),
            'currency' => 'usd',
            'description' => 'CaptureReversal',
            'fee' => -571.43,
            'fee_details' => [
                [
                    'amount' => 57.8,
                    'type' => 'flywire_fee',
                ],
                [
                    'amount' => 0.1,
                    'type' => 'flywire_fee',
                ],
                [
                    'amount' => 26.22,
                    'type' => 'scheme_fees',
                ],
                [
                    'amount' => 487.31,
                    'type' => 'interchange',
                ],
            ],
            'net' => -15944.15,
            'payout_id' => null,
            'reference' => 'QV97RD3J3QG7J424',
            'source_id' => $charge2->id,
            'source_type' => 'charge',
            'type' => 'adjustment',
            'merchant_reference' => null,
        ]);


        /** @var Charge $charge */
        $charge = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', 'CWR7HND4QP7FMCZ3')
            ->one();
        $this->assertEquals(ChargeValueObject::SUCCEEDED, $charge->status);
        $this->assertEquals(500, $charge->amount);
        $this->assertEquals($merchantAccount3->id, $charge->merchant_account?->id);
        $this->assertEquals($paymentFlow->id, $charge->payment_flow?->id);
        $this->assertEquals(self::$customer->id, $charge->customer?->id);

        $transaction5 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'T36CG5GJJC3M26Z3')
            ->oneOrNull();
        $this->checkTransaction($transaction5, $merchantAccount3, [
            'amount' => 500,
            'available_on' => new DateTimeImmutable('2025-05-12'),
            'currency' => 'usd',
            'description' => 'Payment',
            'fee' => 4,
            'fee_details' => [
                [
                    'amount' => 4,
                    'type' => 'flywire_fee',
                ],
            ],
            'net' => 496,
            'payout_id' => null,
            'reference' => 'T36CG5GJJC3M26Z3',
            'source_id' => null,
            'source_type' => null,
            'type' => 'payment',
            'merchant_reference' => 'vp68d1ddu219uu42ir90rdw17m2igulf',
        ]);

        $transaction6 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'LF3BBK9ZKZBZBZQ9')
            ->oneOrNull();
        $this->checkTransaction($transaction6, $merchantAccount3, [
            'amount' => 0,
            'available_on' => new DateTimeImmutable('2025-07-18'),
            'currency' => 'usd',
            'description' => 'Payment',
            'fee' => 6.43,
            'fee_details' => [
            ],
            'net' => -6.43,
            'payout_id' => null,
            'reference' => 'LF3BBK9ZKZBZBZQ9',
            'source_id' => null,
            'source_type' => null,
            'type' => 'payment',
            'merchant_reference' => '8frhf010flndh7mm32ajve77aom4blf0',
        ]);

        $transaction6 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'VX4KCGSJD67BX2F3')
            ->oneOrNull();
        $this->checkTransaction($transaction6, $merchantAccount3, [
            'amount' => -1.36,
            'available_on' => new DateTimeImmutable('2025-07-08'),
            'currency' => 'usd',
            'description' => 'Refund (0.01 Rounding Adjustment)',
            'fee' => 0,
            'fee_details' => [],
            'net' => -1.36,
            'payout_id' => null,
            'reference' => 'VX4KCGSJD67BX2F3',
            'source_id' => null,
            'source_type' => null,
            'type' => 'refund',
            'merchant_reference' => null,
        ]);

        $transaction7 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount3)
            ->where('reference', 'W9QRGQXBVFKR6QZ3')
            ->oneOrNull();
        $this->checkTransaction($transaction7, $merchantAccount3, [
            'amount' => 6950.0,
            'available_on' => new DateTimeImmutable('2025-09-18'),
            'currency' => 'usd',
            'description' => 'Refund Reversal',
            'fee' => 0.0,
            'fee_details' => [],
            'net' => 6950.0,
            'payout_id' => null,
            'reference' => 'W9QRGQXBVFKR6QZ3',
            'source_type' => 'refund',
            'type' => 'refund_reversal',
            'merchant_reference' => null,
        ]);
        $this->checkRefundStatus($refund3, RefundValueObject::VOIDED);
        $this->checkChargeAmountRefunded($chargeRefundReversal, 0);

        // Validate the ledger entries that were created
        $this->checkLedger($merchantAccount1, [
            'Bank Account' => new Money(0, new Currency('usd')),
            'Disputed Payments' => new Money(0, new Currency('usd')),
            'Merchant Account' => new Money(89876, new Currency('usd')),
            //BWXXWWC8RNCLDNF3 - 504.92 + fee 15.08 missing from 02_19 report
            'Processed Payments' => new Money(-92560, new Currency('usd')),
             //15.08 previously uncounted fee added to ledger from balanceplatform_accounting_report_2025_02_19.csv'
            'Processing Fees' => new Money(2684, new Currency('usd')),
            'Refunded Payments' => new Money(0, new Currency('usd')),
            'Rounding Difference' => new Money(0, new Currency('usd')),
        ]);

        $this->checkLedger($merchantAccount2, [
            'Bank Account' => new Money(0, new Currency('usd')),
            'Disputed Payments' => new Money(0, new Currency('usd')),
            'Merchant Account' => new Money(728250, new Currency('usd')),
            'Processed Payments' => new Money(-750000, new Currency('usd')),
            'Processing Fees' => new Money(21750, new Currency('usd')),
            'Refunded Payments' => new Money(0, new Currency('usd')),
            'Rounding Difference' => new Money(0, new Currency('usd')),
        ]);

        $this->checkLedger($merchantAccount3, [
            'Bank Account' => new Money(0, new Currency('usd')),
            'Disputed Payments' => new Money(0, new Currency('usd')),
            'Merchant Account' => new Money(-2650161, new Currency('usd')),
            'Processed Payments' => new Money(3401092, new Currency('usd')),
            'Processing Fees' => new Money(-56067, new Currency('usd')),
            'Refunded Payments' => new Money(-694864, new Currency('usd')),
            'Rounding Difference' => new Money(0, new Currency('usd')),
        ]);
    }
    
    public function testHandleRowRefunds(): void
    {
        $merchantAccount = $this->createMerchantAccount('565-kahsnbpobx');

        $charge1 = $this->makeCharge('D5MZ3M7F6S2SKWX3', 405.6);
        $charge2 = $this->makeCharge('W35RPBB3ZPSTGLV5', 4000);

        $refund1 = $this->makeRefund('D6BVL8LB4ZQJH275', $charge1, 1594);
        $refund2 = $this->makeRefund('NCXTTBB3ZPSTGLV5', $charge2, 1594);

        // Process all the reports
        $this->handleFile('balanceplatform_accounting_report_refunds.csv');

        // Validate the transactions that were created
        $refund1->refresh();
        $transaction1 = $refund1->merchant_account_transaction;
        $this->checkTransaction($transaction1, $merchantAccount, [
            'amount' => -1594.0,
            'available_on' => new CarbonImmutable('2025-02-12'),
            'currency' => 'usd',
            'description' => 'Refund',
            'fee' => -46.23,
            'fee_details' => [
                [
                    'amount' => -46.23,
                    'type' => 'flywire_fee',
                ],
            ],
            'net' => -1547.77,
            'payout_id' => null,
            'reference' => 'D6BVL8LB4ZQJH275',
            'source_id' => $refund1->id,
            'source_type' => 'refund',
            'type' => 'refund',
            'merchant_reference' => null,
        ]);

        $refund2->refresh();
        $transaction2 = $refund2->merchant_account_transaction;
        $this->checkTransaction($transaction2, $merchantAccount, [
            'amount' => 1234,
            'available_on' => new CarbonImmutable('2025-02-11'),
            'currency' => 'usd',
            'description' => 'Refund',
            'fee' => 35.79,
            'fee_details' => [
                [
                    'amount' => 35.79,
                    'type' => 'flywire_fee',
                ],
            ],
            'net' => 1198.21,
            'payout_id' => null,
            'reference' => 'NCXTTBB3ZPSTGLV5',
            'source_id' => $refund2->id,
            'source_type' => 'refund',
            'type' => 'refund',
            'merchant_reference' => null,
        ]);

        // Validate the ledger entries that were created
        $this->checkLedger($merchantAccount, [
            'Bank Account' => new Money(0, new Currency('usd')),
            'Disputed Payments' => new Money(0, new Currency('usd')),
            'Merchant Account' => new Money(-54376, new Currency('usd')),
            'Processed Payments' => new Money(0, new Currency('usd')),
            'Processing Fees' => new Money(-1624, new Currency('usd')),
            'Refunded Payments' => new Money(56000, new Currency('usd')),
            'Rounding Difference' => new Money(0, new Currency('usd')),
        ]);
    }

    public function testHandleRowChargebacks(): void
    {
        $merchantAccount = $this->createMerchantAccount('5434632-sclbsythsm');

        $charge1 = $this->makeCharge('T56ZVH3GGR7ZZFX3', 2500);
        $charge2 = $this->makeCharge('T56ZVH3GGR7ZZFX3', 2500);
        $charge3 = $this->makeCharge('J4CBBF5BNHHQ3WD3', 186.7, 'bank_account');

        $dispute1 = $this->makeDispute('Z6DVS958KMQXWVW3', $charge1, 2500);
        $dispute2 = $this->makeDispute('NZVPZDDDLPDLJJV5', $charge2, 2500);

        // Process all the reports
        $this->handleFile('balanceplatform_accounting_report_2025_04_07.csv');

        // Validate the transactions that were created
        $dispute1->refresh();
        $transaction1 = $dispute1->merchant_account_transaction;
        $this->checkTransaction($transaction1, $merchantAccount, [
            'amount' => -2500,
            'available_on' => new CarbonImmutable('2025-04-07'),
            'currency' => 'usd',
            'description' => 'Dispute',
            'fee' => 0,
            'fee_details' => [],
            'net' => -2500,
            'payout_id' => null,
            'reference' => 'Z6DVS958KMQXWVW3',
            'source_id' => $dispute1->id,
            'source_type' => 'dispute',
            'type' => 'dispute',
            'merchant_reference' => null,
        ]);

        $transaction2 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
            ->where('reference', 'MCXC47K46LGVNLV5')
            ->oneOrNull();
        $this->checkTransaction($transaction2, $merchantAccount, [
            'amount' => 2500,
            'available_on' => new CarbonImmutable('2025-02-15'),
            'currency' => 'usd',
            'description' => 'Dispute Reversal',
            'fee' => 0,
            'fee_details' => [],
            'net' => 2500,
            'payout_id' => null,
            'reference' => 'MCXC47K46LGVNLV5',
            'source_id' => $dispute1->id,
            'source_type' => 'dispute',
            'type' => 'dispute_reversal',
            'merchant_reference' => null,
        ]);

        $dispute2->refresh();
        $transaction3 = $dispute2->merchant_account_transaction;
        $this->checkTransaction($transaction3, $merchantAccount, [
            'amount' => -2500,
            'available_on' => new CarbonImmutable('2025-02-20'),
            'currency' => 'usd',
            'description' => 'Dispute',
            'fee' => 0,
            'fee_details' => [],
            'net' => -2500,
            'payout_id' => null,
            'reference' => 'NZVPZDDDLPDLJJV5',
            'source_id' => $dispute2->id,
            'source_type' => 'dispute',
            'type' => 'dispute',
            'merchant_reference' => null,
        ]);


        $transaction4 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
            ->where('reference', 'J4CBBF5BNHHQ3WD3')
            ->oneOrNull();
        $this->checkTransaction($transaction4, $merchantAccount, [
            'amount' => -186.7,
            'available_on' => new CarbonImmutable('2025-04-07'),
            'currency' => 'usd',
            'description' => 'Direct Debit Chargeback',
            'fee' => 0,
            'fee_details' => [],
            'net' => -186.7,
            'payout_id' => null,
            'reference' => 'J4CBBF5BNHHQ3WD3',
            'source_id' => $charge3->id,
            'source_type' => 'charge',
            'type' => 'dispute',
            'merchant_reference' => null,
        ]);

        $charge3->refresh();
        $this->assertEquals('failed', $charge3->status);

        // Validate the ledger entries that were created
        $this->checkLedger($merchantAccount, [
            'Bank Account' => new Money(0, new Currency('usd')),
            'Disputed Payments' => new Money(268670, new Currency('usd')),
            'Merchant Account' => new Money(-104766, new Currency('usd')),
            'Processed Payments' => new Money(-165400, new Currency('usd')),
            'Processing Fees' => new Money(1496, new Currency('usd')),
            'Refunded Payments' => new Money(0, new Currency('usd')),
            'Rounding Difference' => new Money(0, new Currency('usd')),
        ]);
    }

    public function testHandleRowInternalTransfer(): void
    {
        $merchantAccount = $this->createMerchantAccount('1-frazlqhuji');

        // Process all the reports
        $this->handleFile('balanceplatform_accounting_report_2025_02_11.csv');

        // Validate the transactions that were created
        $transaction1 = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
            ->where('reference', '3K56SK65Y8RO9XF9')
            ->oneOrNull();
        $this->checkTransaction($transaction1, $merchantAccount, [
            'amount' => 10,
            'available_on' => new CarbonImmutable('2025-02-11'),
            'currency' => 'usd',
            'description' => 'Test case',
            'fee' => 0,
            'fee_details' => [],
            'net' => 10,
            'payout_id' => null,
            'reference' => '3K56SK65Y8RO9XF9',
            'source_id' => null,
            'source_type' => null,
            'type' => 'adjustment',
            'merchant_reference' => null,
        ]);

        // Validate the ledger entries that were created
        $this->checkLedger($merchantAccount, [
            'Bank Account' => new Money(0, new Currency('usd')),
            'Disputed Payments' => new Money(0, new Currency('usd')),
            'Merchant Account' => new Money(1000, new Currency('usd')),
            'Processed Payments' => new Money(-1000, new Currency('usd')),
            'Processing Fees' => new Money(0, new Currency('usd')),
            'Refunded Payments' => new Money(0, new Currency('usd')),
            'Rounding Difference' => new Money(0, new Currency('usd')),
        ]);
    }

    protected function getHandler(): AccountingReportHandler
    {
        $createPayout = Mockery::mock(SaveAdyenPayout::class);
        $createPayout->shouldReceive('save')
            ->andReturn(null);
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send');
        $mockDisputeReconciler = $this->createMock(DisputeReconciler::class);

        $ledger = $this->getLedger();
        $spool = Mockery::mock(NotificationSpool::class);
        $spool->shouldReceive('spool')
            ->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturnSelf();
        $chargebackHandler = new ChargebackGroupHandler(self::getService('test.update_charge_status'), $ledger, true, $mockDisputeReconciler);
        $chargebackReversalHandler = new ChargebackReversalGroupHandler($ledger, true);
        $internalHandler = new InternalTransferGroupHandler($ledger, true);
        $paymentHandler = new PaymentGroupHandler(self::getService('test.payment_flow_reconcile'), self::getService('test.adyen_save_payment'), $ledger, true);
        $payoutHandler = new PayoutGroupHandler($ledger, true, $createPayout);
        $refundHandler = new RefundGroupHandler($ledger, true);
        $voidRefund = new VoidRefund($spool, self::getService('test.customer_portal_events'));
        $voidRefund->setStatsd(self::getService('test.statsd_client'));
        $refundReversalHandler = new RefundReversalGroupHandler($ledger, $voidRefund, true);
        $topUpHandler = new TopUpGroupHandler($ledger, true);
        $paymentReversalHandler = new PaymentReversalGroupHandler($ledger, true, $mailer);

        return new AccountingReportHandler(
            self::getService('test.tenant'),
            true,
            self::getService('test.transaction_manager'),
            $chargebackHandler,
            $chargebackReversalHandler,
            $internalHandler,
            $paymentHandler,
            $payoutHandler,
            $refundHandler,
            $refundReversalHandler,
            $topUpHandler,
            $paymentReversalHandler,
        );
    }

    private function getLedger(): MerchantAccountLedger
    {
        return self::getService('test.merchant_account_ledger');
    }

    private function makeCharge(string $reference, float $amount, string $type = 'card', float $amountRefunded = 0.0): Charge
    {
        $charge = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $reference)
            ->oneOrNull();

        if ($charge) {
            return $charge;
        }

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = $amount;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = $reference;
        $charge->payment_source_type = $type;
        $charge->amount_refunded = $amountRefunded;
        $charge->saveOrFail();

        return $charge;
    }

    private function makeRefund(string $reference, Charge $charge, float $amount): Refund
    {
        $refund = new Refund();
        $refund->charge = $charge;
        $refund->currency = 'usd';
        $refund->amount = $amount;
        $refund->status = 'succeeded';
        $refund->gateway = AdyenGateway::ID;
        $refund->gateway_id = $reference;
        $refund->saveOrFail();

        return $refund;
    }

    private function makeDispute(string $reference, Charge $charge, float $amount): Dispute
    {
        $dispute = new Dispute();
        $dispute->charge = $charge;
        $dispute->currency = 'usd';
        $dispute->amount = $amount;
        $dispute->gateway = AdyenGateway::ID;
        $dispute->gateway_id = $reference;
        $dispute->status = DisputeStatus::Undefended;
        $dispute->reason = 'Fraudulent';
        $dispute->saveOrFail();

        return $dispute;
    }

    private function checkLedger(MerchantAccount $merchantAccount, array $expectedBalances): void
    {
        $ledger = $this->getLedger()->getLedger($merchantAccount);
        $balances = $ledger->reporting->getAccountBalances(CarbonImmutable::now());
        $expected = [];
        foreach ($expectedBalances as $name => $balance) {
            $expected[] = [
                'name' => $name,
                'balance' => $balance,
            ];
        }
        $this->assertEquals($expected, $balances);
    }

    private function checkTransaction(?MerchantAccountTransaction $transaction, MerchantAccount $merchantAccount, array $expected): void
    {
        $this->assertInstanceOf(MerchantAccountTransaction::class, $transaction);
        $expected = array_merge($expected, [
            'created_at' => $transaction->created_at,
            'id' => $transaction->id,
            'merchant_account_id' => $merchantAccount->id,
            'object' => 'merchant_account_transaction',
            'updated_at' => $transaction->updated_at,
            'source_id' => $transaction->source_id,
        ]);
        $this->assertEquals($expected, $transaction->toArray());
    }
    
    private function checkRefundStatus(Refund $refund, string $expectedStatus): void
    {
        $refund->refresh();
        $this->assertEquals($expectedStatus, $refund->status);
    }
    
    private function checkChargeAmountRefunded(Charge $charge, float $expectedAmountRefunded): void
    {
        $charge->refresh();
        $this->assertEquals($expectedAmountRefunded, $charge->amount_refunded);
    }
}
