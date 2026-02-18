<?php

namespace App\Tests\PaymentProcessing\Gateways;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Operations\SaveAlreadyExistingPaymentSource;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class AdyenGatewayTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::hasMerchantAccount(AdyenGateway::ID);
    }

    public function testMakeChargebackLogic(): void
    {
        $gateway = $this->getGateway();
        $merchantAccount = new MerchantAccount([
            'gateway' => AdyenGateway::ID,
            'credentials' => (object) ['balance_account' => 'BA00000000000000000000001'],
        ]);
        $expected = [
            'behavior' => 'deductFromOneBalanceAccount',
            'targetAccount' => 'BA00000000000000000000001',
        ];
        $chargebackLogic = $gateway->makeChargebackLogic($merchantAccount);
        $this->assertEquals($expected, $chargebackLogic);
    }

    public function testMakeSplits(): void
    {
        $gateway = $this->getGateway();
        $merchantAccount = new MerchantAccount([
            'gateway' => AdyenGateway::ID,
            'credentials' => (object) ['balance_account' => 'BA00000000000000000000001'],
        ]);
        $amount = new Money('usd', 100000);
        $fee = new Money('usd', 500);
        $expected = [
            [
                'amount' => [
                    'value' => 99500,
                ],
                'type' => 'BalanceAccount',
                'account' => 'BA00000000000000000000001',
                'description' => 'Seller split',
            ],
            [
                'amount' => [
                    'value' => 500,
                ],
                'type' => 'Commission',
                'description' => 'Variable Fee',
            ],
        ];
        $splits = $gateway->makeSplits($merchantAccount, $amount, $fee);
        foreach ($splits as &$split) {
            unset($split['reference']);
        }
        $this->assertEquals($expected, $splits);
    }

    public function testMakeLevel3(): void
    {
        $gateway = $this->getGateway();
        $documents = [self::$invoice];
        $expected = [
            'enhancedSchemeData.customerReference' => 'INV-00001',
            'enhancedSchemeData.destinationCountryCode' => 'USA',
            'enhancedSchemeData.destinationPostalCode' => '78701',
            'enhancedSchemeData.destinationStateProvinceCode' => 'TX',
            'enhancedSchemeData.dutyAmount' => '0',
            'enhancedSchemeData.freightAmount' => '0',
            'enhancedSchemeData.orderDate' => date('dmy'),
            'enhancedSchemeData.shipFromPostalCode' => '78701',
            'enhancedSchemeData.totalTaxAmount' => '1000',
            'enhancedSchemeData.itemDetailLine1.commodityCode' => '80161501',
            'enhancedSchemeData.itemDetailLine1.description' => 'Test Item',
            'enhancedSchemeData.itemDetailLine1.discountAmount' => '0',
            'enhancedSchemeData.itemDetailLine1.quantity' => '1',
            'enhancedSchemeData.itemDetailLine1.totalAmount' => '9000',
            'enhancedSchemeData.itemDetailLine1.unitOfMeasure' => 'EA',
            'enhancedSchemeData.itemDetailLine1.unitPrice' => '9000',
        ];

        /** @var array $level3 */
        $level3 = $gateway->makeLevel3($documents, self::$customer, Money::fromDecimal('usd', 100));
        $this->assertNotEmpty($level3['enhancedSchemeData.itemDetailLine1.productCode']);
        unset($level3['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertEquals($expected, $level3);
    }

    public function testGetTransactionStatus(): void
    {
        $gateway = $this->getGateway();

        $charge = new Charge();
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = AdyenGateway::ID;
        $charge->gateway_id = 'payment';
        $charge->saveOrFail();

        $status = $gateway->getTransactionStatus(self::$merchantAccount, $charge);
        $this->assertEquals([Charge::PENDING, null], $status);

        $this->makeMerchantAccount(MerchantAccountTransactionType::Payment, 'payment', $charge);
        $status = $gateway->getTransactionStatus(self::$merchantAccount, $charge);
        $this->assertEquals([Charge::PENDING, null], $status);

        //advance one day
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
        $status = $gateway->getTransactionStatus(self::$merchantAccount, $charge);
        $this->assertEquals([Charge::SUCCEEDED, null], $status);

        $this->makeMerchantAccount(MerchantAccountTransactionType::Dispute, 'dispute', $charge);
        $status = $gateway->getTransactionStatus(self::$merchantAccount, $charge);
        $this->assertEquals([Charge::FAILED, 'Payment'], $status);
    }

    /**
     * @depends testGetTransactionStatus
     */
    public function testVaultSourceCard(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $gateway = new AdyenGateway(
            $client,
            Mockery::mock(PaymentSourceReconciler::class),
            Mockery::mock(RoutingNumberLookup::class),
            Mockery::mock(PaymentFlowReconcile::class),
            false,
            Mockery::mock(SaveAlreadyExistingPaymentSource::class),
        );


        $reference = 'o7oc00hz2vumlf3qzo9l2ubq'.microtime(true);
        /** @var PaymentFlowManager $manager */
        $manager = self::getService('test.payment_flow_manager');
        // new charge, should be created
        $manager->saveResult($reference, [
            'pspReference' => 'XXX0000000000000',
            'resultCode' => 'Authorised',
            'paymentMethod' => [
                'brand' => 'Visa',
            ],
            'amount' => [
                'currency' => 'usd',
                'value' => 0,
            ],
            'additionalData' => [
                'fundingSource' => 'credit',
                'cardSummary' => '1111',
                'cardIssuingCountry' => 'US',
                'recurring.recurringDetailReference' => '8415995476874711',
                'recurring.shopperReference' => '3123216736128738271',
            ],
        ]);

        /** @var CardValueObject $card */
        $card = $gateway->vaultSource(self::$customer, MerchantAccount::one(), [
            'payment_method' => 'card',
            'reference' => $reference,
        ]);

        $this->assertEquals(self::$customer, $card->customer);
        $this->assertEquals(AdyenGateway::ID, $card->gateway);
        $this->assertEquals('Visa', $card->brand);
        $this->assertEquals('8415995476874711', $card->gatewayId);
        $this->assertEquals('3123216736128738271', $card->gatewayCustomer);
        $this->assertTrue($card->chargeable);
        $this->assertEquals('credit', $card->funding);
        $this->assertEquals('1111', $card->last4);



        $reference = 'o7oc00hz2vumlf3qzo9l2ubq'.microtime(true);
        // new charge, should be created
        $manager->saveResult($reference, [
            'pspReference' => 'XXX0000000000000',
            'resultCode' => 'Authorised',
            'paymentMethod' => [
                'brand' => 'Visa',
            ],
            'amount' => [
                'currency' => 'usd',
                'value' => 0,
            ],
            'additionalData' => [
                'fundingSource' => 'credit',
                'cardSummary' => '1111',
                'cardIssuingCountry' => 'US',
                'recurring.shopperReference' => '3123216736128738271',
            ],
        ]);

        $client->shouldReceive('getRecurringDetails')->andReturn([
            'details' => [
                [
                    'RecurringDetail' => [
                        'name' => $reference,
                        'recurringDetailReference' => '8415995476874711',
                    ],
                ],
            ]
        ])->once();
        /** @var CardValueObject $card */
        $card = $gateway->vaultSource(self::$customer, MerchantAccount::one(), [
            'payment_method' => 'card',
            'reference' => $reference,
        ]);

        $this->assertEquals(self::$customer, $card->customer);
        $this->assertEquals(AdyenGateway::ID, $card->gateway);
        $this->assertEquals('Visa', $card->brand);
        $this->assertEquals('8415995476874711', $card->gatewayId);
        $this->assertEquals('3123216736128738271', $card->gatewayCustomer);
        $this->assertTrue($card->chargeable);
        $this->assertEquals('credit', $card->funding);
        $this->assertEquals('1111', $card->last4);
    }

    public function testVaultSourceACH(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $routingNr = Mockery::mock(RoutingNumberLookup::class);
        $gateway = new AdyenGateway(
            $client,
            Mockery::mock(PaymentSourceReconciler::class),
            $routingNr,
            Mockery::mock(PaymentFlowReconcile::class),
            false,
            Mockery::mock(SaveAlreadyExistingPaymentSource::class),
        );

        $reference = 'o7oc00hz2vumlf3qzo9l2ubq'.microtime(true);

        $client->shouldReceive('verifyBankAccount')->andReturn([
                'additionalData' => [
                    'bankVerificationResult' => 'Passed',
                    'recurring.shopperReference' => $reference,
                    'recurring.recurringDetailReference' => '8415995476874711',
                ]
        ])->once();
        $routingNr->shouldReceive('lookup')->andReturn(null);

        /** @var BankAccountValueObject $ach */
        $ach = $gateway->vaultSource(self::$customer, MerchantAccount::one(), [
            'payment_method' => 'ach',
            'account_number' => 1234567890,
            'routing_number' => '011000138',
            'account_holder_name' => 'test user',
        ]);

        $this->assertEquals(self::$customer, $ach->customer);
        $this->assertEquals(AdyenGateway::ID, $ach->gateway);
        $this->assertEquals('8415995476874711', $ach->gatewayId);
        $this->assertEquals($reference, $ach->gatewayCustomer);
        $this->assertTrue($ach->chargeable);
        $this->assertEquals('7890', $ach->last4);
    }
  
    public function testVaultSourceACHWhenThrowsException(): void
    {
        $this->expectException(PaymentSourceException::class);
        $client = Mockery::mock(AdyenClient::class);
        $routingNr = Mockery::mock(RoutingNumberLookup::class);
        $gateway = new AdyenGateway(
            $client,
            Mockery::mock(PaymentSourceReconciler::class),
            $routingNr,
            Mockery::mock(PaymentFlowReconcile::class),
            false,
            Mockery::mock(SaveAlreadyExistingPaymentSource::class),
        );
        
        $client->shouldReceive('verifyBankAccount')->andThrows(new IntegrationApiException('Some error'));
        $routingNr->shouldReceive('lookup')->andReturn(null);
        
        $gateway->vaultSource(self::$customer, MerchantAccount::one(), [
            'payment_method' => 'ach',
            'account_number' => 1234567890,
            'routing_number' => '011000138',
            'account_holder_name' => 'test user',
        ]);
    }

    public function testVaultSourceACHVerificationNotEnabled(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $gateway = new AdyenGateway(
            $client,
            Mockery::mock(PaymentSourceReconciler::class),
            Mockery::mock(RoutingNumberLookup::class),
            Mockery::mock(PaymentFlowReconcile::class),
            false,
            Mockery::mock(SaveAlreadyExistingPaymentSource::class),
        );

        $reference = 'o7oc00hz2vumlf3qzo9l2ubq'.microtime(true);
        $client->shouldReceive('verifyBankAccount')->andReturn([
            'additionalData' => [
                'recurring.shopperReference' => $reference,
                'recurring.recurringDetailReference' => '8415995476874711',
            ]
        ])->once();

        $this->expectException(PaymentSourceException::class);
        $this->expectExceptionMessage('Verification not enabled for your account. Ask support to enable gverify for your store.');
        $gateway->vaultSource(self::$customer, MerchantAccount::one(), [
            'payment_method' => 'ach',
            'account_number' => 1234567890,
            'routing_number' => '011000138',
            'account_holder_name' => 'test user',
        ]);
    }

    private function makeMerchantAccount(MerchantAccountTransactionType $type, string $reference, Charge $charge): void
    {
        $transaction1 = new MerchantAccountTransaction();
        $transaction1->merchant_account = self::$merchantAccount;
        $transaction1->reference = $reference;
        $transaction1->type = $type;
        $transaction1->currency = 'usd';
        $transaction1->amount = 10;
        $transaction1->fee = 0;
        $transaction1->net = 10;
        $transaction1->description = 'Payment';
        $transaction1->available_on = CarbonImmutable::now();
        $transaction1->setSource($charge);
        $transaction1->saveOrFail();
    }

    private function getGateway(): AdyenGateway
    {
        return self::getService('test.payment_gateway_factory')->get(AdyenGateway::ID);
    }
}
