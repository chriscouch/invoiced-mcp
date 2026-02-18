<?php

namespace App\Tests\Integrations\Adyen\Operations;

use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Enums\ChargebackEvent;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\SaveAdyenDisputeFee;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\DisputeFee;
use App\Tests\AppTestCase;
use Mockery;

class SaveAdyenAccountFeeTest extends AppTestCase
{
    private static Dispute $dispute;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        $adyenAccount = new AdyenAccount();
        $adyenAccount->saveOrFail();

        self::hasMerchantAccount(AdyenGateway::ID, 'TEST_MERCHANT_ID', ['balance_account' => 'test_account']);

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = FlywireGateway::ID;
        $charge->gateway_id = 'test';
        $charge->last_status_check = 0;
        $charge->merchant_account = self::$merchantAccount;
        $charge->saveOrFail();

        self::$dispute = new Dispute();
        self::$dispute->currency = 'usd';
        self::$dispute->amount = 100;
        self::$dispute->gateway_id = '1234';
        self::$dispute->gateway = 'test';
        self::$dispute->charge = $charge;
        self::$dispute->saveOrFail();
    }

    private function getOperation(AdyenClient $client): SaveAdyenDisputeFee
    {
        return new SaveAdyenDisputeFee($client, false, self::getService('test.mailer'));
    }

    public function testError(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $client->shouldReceive('makeTransfer')->andThrow(new IntegrationApiException('test'));
        $operation = $this->getOperation($client);
        $operation->save(self::$dispute, ChargebackEvent::CHARGEBACK);

        $fees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->where('success', 0)
            ->execute();

        $this->assertCount(1, $fees);
        $this->assertEquals('test', $fees[0]->reason);
    }

    public function testNoFee(): void
    {
        $initialFees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->count();

        $client = Mockery::mock(AdyenClient::class);
        $operation = $this->getOperation($client);
        $operation->save(self::$dispute, ChargebackEvent::REQUEST_FOR_INFORMATION);

        $fees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->count();

        $this->assertEquals($initialFees, $fees);
    }

    public function testFirstFee(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $client->shouldReceive('makeTransfer')
            ->with([
                'amount' => [
                    'value' => 1500,
                    'currency' => 'USD',
                ],
                'balanceAccountId' => 'test_account',
                'counterparty' => [
                    'balanceAccountId' => 'BA32CQ3223228N5LZRBFRD3JG',
                ],
                'category' => 'internal',
            ])
            ->andReturn([
                'reference' => 'test',
            ]);
        $operation = $this->getOperation($client);
        $operation->save(self::$dispute, ChargebackEvent::CHARGEBACK);

        $fees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->where('success', 1)
            ->where('dispute_id', self::$dispute->id)
            ->execute();

        $this->assertCount(1, $fees);
        $this->assertEquals('test', $fees[0]->gateway_id);
        $this->assertEquals(15, $fees[0]->amount);
        $this->assertEquals('usd', $fees[0]->currency);
    }

    /**
     * @depends testFirstFee
     */
    public function testDuplicatedOneFee(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $client->shouldReceive('makeTransfer')->andReturn([
            'reference' => 'test',
        ]);
        $operation = $this->getOperation($client);
        $operation->save(self::$dispute, ChargebackEvent::CHARGEBACK);

        $fees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->where('success', 1)
            ->where('dispute_id', self::$dispute->id)
            ->execute();

        $this->assertCount(1, $fees);
    }

    /**
     * @depends testDuplicatedOneFee
     */
    public function testSecondFee(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $client->shouldReceive('makeTransfer')->andReturn([
            'reference' => 'test2',
        ]);
        $operation = $this->getOperation($client);
        $operation->save(self::$dispute, ChargebackEvent::SECOND_CHARGEBACK);

        $fees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->where('success', 1)
            ->where('dispute_id', self::$dispute->id)
            ->count();

        $this->assertEquals(2, $fees);
    }

    /**
     * @depends testSecondFee
     */
    public function testDuplicatedSecondFee(): void
    {
        $client = Mockery::mock(AdyenClient::class);
        $client->shouldReceive('makeTransfer')->andReturn([
            'reference' => 'test3',
        ]);
        $operation = $this->getOperation($client);
        $operation->save(self::$dispute, ChargebackEvent::SECOND_CHARGEBACK);

        $fees = DisputeFee::where('dispute_id', self::$dispute->id)
            ->where('success', 1)
            ->where('dispute_id', self::$dispute->id)
            ->count();

        $this->assertEquals(2, $fees);
    }
}
