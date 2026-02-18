<?php

namespace App\Tests\Integrations\GoCardless;

use App\Integrations\GoCardless\GoCardlessWebhook;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use App\Tests\AppTestCase;
use Mockery;

class GoCardlessWebhookTest extends AppTestCase
{
    private string $secret = 'ab7fDl-RdenjYUK2GKmfUDJgRqZ0i15K4AU44NdC';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount('gocardless', 'user_1234');
        self::hasCustomer();
        self::hasBankAccount(GoCardlessGateway::ID);
    }

    private function getHandler(?UpdateChargeStatus $updateChargeStatus = null): GoCardlessWebhook
    {
        if (!$updateChargeStatus) {
            $updateChargeStatus = self::getService('test.update_charge_status');
            $updateChargeStatus->setGatewayFactory(self::getService('test.payment_gateway_factory'));
        }

        $handler = new GoCardlessWebhook($this->secret, $updateChargeStatus, self::getService('test.lock_factory'), self::getService('test.transaction_manager'), 'invoicedtest');
        $handler->setLogger(self::$logger);

        return $handler;
    }

    private function getUpdateChargeStatus(PaymentGatewayInterface $gateway): UpdateChargeStatus
    {
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);
        $updateChargeStatus = self::getService('test.update_charge_status');
        $updateChargeStatus->setGatewayFactory($gatewayFactory);

        return $updateChargeStatus;
    }

    public function testValidateSignature(): void
    {
        $signature = 'a7645dbe7b1b9a3f26ddd447b20c156f59570ad19e709060f977a7713f41fbaa';
        $payload = '{"events":[{"id":"EVTESTE5SMAWDF","created_at":"2018-05-18T19:32:28.639Z","resource_type":"mandates","action":"cancelled","links":{"mandate":"index_ID_123","organisation":"OR00003HCTG5FJ"},"details":{"origin":"api","cause":"mandate_cancelled","description":"The mandate was cancelled at your request."},"metadata":{}}]}';
        $handler = $this->getHandler();
        $this->assertTrue($handler->validateSignature($signature, $payload));
        $this->assertFalse($handler->validateSignature('not valid', $payload));
    }

    //
    // WebhookHandlerInterface
    //

    public function testGetCompanies(): void
    {
        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'confirmed',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM123',
                'organisation' => 'user_12345',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];
        $this->assertCount(0, $this->getHandler()->getCompanies($event));

        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'confirmed',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM123',
                'organisation' => 'user_1234',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $companies = $this->getHandler()->getCompanies($event);
        $this->assertCount(1, $companies);
        $this->assertEquals(self::$company->id(), $companies[0]->id());
    }

    public function testShouldProcess(): void
    {
        $event = [
            'id' => uniqid(),
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'confirmed',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM123',
                'organisation' => 'user_1234',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $this->assertTrue($this->getHandler()->shouldProcess($event));

        // should not be processed again
        $this->assertEquals(1, self::getService('test.redis')->exists('invoicedtest:gocardless_event.'.$event['id']));
        $this->assertFalse($this->getHandler()->shouldProcess($event));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testProcessWebhook(): void
    {
        $event = [
            'id' => uniqid(),
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'confirmed',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM123',
                'organisation' => 'user_1234',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $this->getHandler()->process(self::$company, $event);
    }

    public function testMandateActivated(): void
    {
        $event = [
            'id' => 'EV001K3P352NNT',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'active',
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'mandate_activated',
                'description' => 'The time window after submission for the banks to refuse a mandate has ended without any errors being received, so this mandate is now active.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003JBN1C6NC',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003JBN1C6NC';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = false;
        $bankAccount->verified = false;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_active($event);

        $this->assertTrue($bankAccount->refresh()->chargeable);
        $this->assertTrue($bankAccount->verified);
    }

    public function testMandateCancelled(): void
    {
        $event = [
            'id' => 'EV001K3P352NNT',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'cancelled',
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'bank_account_closed',
                'description' => 'The bank account for this mandate has been closed.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003JBN1C6NE',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003JBN1C6NE';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = true;
        $bankAccount->verified = false;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_cancelled($event);

        $this->assertFalse($bankAccount->refresh()->chargeable);
        $this->assertTrue($bankAccount->verified);
        $this->assertEquals('The bank account for this mandate has been closed.', $bankAccount->failure_reason);
    }

    public function testMandateFailed(): void
    {
        $event = [
            'id' => 'EV001K3P352NNT',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'failed',
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'bank_account_closed',
                'description' => 'The bank account for this mandate has been closed.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003JBN1C6NF',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003JBN1C6NF';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = true;
        $bankAccount->verified = false;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_failed($event);

        $this->assertFalse($bankAccount->refresh()->chargeable);
        $this->assertTrue($bankAccount->verified);
        $this->assertEquals('The bank account for this mandate has been closed.', $bankAccount->failure_reason);
    }

    public function testMandateExpired(): void
    {
        $event = [
            'id' => 'EV001K3P352NNT',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'expired',
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'mandate_expired',
                'description' => 'The bank account for this mandate has been closed.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003JBN1C6ND',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003JBN1C6ND';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = true;
        $bankAccount->verified = false;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_expired($event);

        $this->assertFalse($bankAccount->refresh()->chargeable);
        $this->assertTrue($bankAccount->verified);
        $this->assertEquals('The bank account for this mandate has been closed.', $bankAccount->failure_reason);
    }

    public function testMandateResubmissionRequested(): void
    {
        $event = [
            'id' => 'EV001P1PDNTM8B',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'resubmission_requested',
            'details' => [
                'origin' => 'api',
                'cause' => 'resubmission_requested',
                'description' => 'An attempt to reinstate this mandate was requested.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003NZ1XRSFD',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003NZ1XRSFD';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = false;
        $bankAccount->failure_reason = 'The mandate has expired.';
        $bankAccount->verified = true;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_resubmission_requested($event);

        $this->assertTrue($bankAccount->refresh()->chargeable);
        $this->assertNull($bankAccount->failure_reason);
        $this->assertFalse($bankAccount->verified);
    }

    public function testMandateReinstated(): void
    {
        $event = [
            'id' => 'EV001K3P352NNT',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'reinstated',
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'mandate_reinstated',
                'description' => 'The time window after submission for the banks to refuse a mandate has ended without any errors being received, so this mandate is now active.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003JBN1C6NA',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003JBN1C6NA';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = false;
        $bankAccount->verified = false;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_active($event);

        $this->assertTrue($bankAccount->refresh()->chargeable);
        $this->assertTrue($bankAccount->verified);
    }

    public function testMandateReplaced(): void
    {
        $event = [
            'id' => 'EV001K3P352NNT',
            'created_at' => '2018-06-14T18:39:05.099Z',
            'resource_type' => 'mandates',
            'action' => 'replaced',
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'mandate_replaced',
                'description' => 'The time window after submission for the banks to refuse a mandate has ended without any errors being received, so this mandate is now active.',
            ],
            'metadata' => [],
            'links' => [
                'mandate' => 'MD0003JBN1C6NB',
                'new_mandate' => 'MD0003JBN1C6NC',
                'organisation' => 'OR00003J4760GR',
            ],
        ];

        $bankAccount = new BankAccount();
        $bankAccount->customer = self::$customer;
        $bankAccount->gateway = GoCardlessGateway::ID;
        $bankAccount->gateway_id = 'MD0003JBN1C6NB';
        $bankAccount->last4 = '1234';
        $bankAccount->bank_name = 'Barclays';
        $bankAccount->currency = 'gbp';
        $bankAccount->chargeable = false;
        $bankAccount->verified = false;
        $bankAccount->merchant_account_id = (int) self::$merchantAccount->id();
        $bankAccount->saveOrFail();

        $this->getHandler()->mandates_replaced($event);

        $this->assertTrue($bankAccount->refresh()->chargeable);
        $this->assertTrue($bankAccount->verified);
        $this->assertEquals('MD0003JBN1C6NC', $bankAccount->gateway_id);
    }

    public function testPaymentChargedBack(): void
    {
        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'charged_back',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM126',
                'organisation' => 'user_12345',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->gateway = GoCardlessGateway::ID;
        $charge->gateway_id = 'PM126';
        $charge->amount = 100;
        $charge->currency = 'usd';
        $charge->status = Charge::SUCCEEDED;
        $charge->setPaymentSource(self::$bankAccount);
        $charge->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturn([Charge::FAILED, '']);
        $pendingTransactions = $this->getUpdateChargeStatus($gateway);

        $handler = $this->getHandler($pendingTransactions);
        $handler->payments_charged_back($event);

        $this->assertEquals(Charge::FAILED, $charge->refresh()->status);
    }

    public function testPaymentLateFailureSettled(): void
    {
        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'late_failure_settled',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM128',
                'organisation' => 'user_12345',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->gateway = GoCardlessGateway::ID;
        $charge->gateway_id = 'PM128';
        $charge->amount = 100;
        $charge->currency = 'usd';
        $charge->status = Charge::SUCCEEDED;
        $charge->setPaymentSource(self::$bankAccount);
        $charge->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturn([Charge::FAILED, '']);
        $pendingTransactions = $this->getUpdateChargeStatus($gateway);

        $handler = $this->getHandler($pendingTransactions);
        $handler->payments_charged_back($event);

        $this->assertEquals(Charge::FAILED, $charge->refresh()->status);
    }

    public function testPaymentChargebackSettled(): void
    {
        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'chargeback_settled',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM129',
                'organisation' => 'user_12345',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->gateway = GoCardlessGateway::ID;
        $charge->gateway_id = 'PM129';
        $charge->amount = 100;
        $charge->currency = 'usd';
        $charge->status = Charge::SUCCEEDED;
        $charge->setPaymentSource(self::$bankAccount);
        $charge->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturn([Charge::FAILED, '']);
        $pendingTransactions = $this->getUpdateChargeStatus($gateway);

        $handler = $this->getHandler($pendingTransactions);
        $handler->payments_charged_back($event);

        $this->assertEquals(Charge::FAILED, $charge->refresh()->status);
    }

    public function testPaymentConfirmed(): void
    {
        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'confirmed',
            'resource_type' => 'payments',
            'links' => [
                'payment' => 'PM130',
                'organisation' => 'user_12345',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->gateway = GoCardlessGateway::ID;
        $charge->gateway_id = 'PM130';
        $charge->amount = 100;
        $charge->currency = 'usd';
        $charge->status = Charge::PENDING;
        $charge->setPaymentSource(self::$bankAccount);
        $charge->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturn([Charge::SUCCEEDED, '']);
        $pendingTransactions = $this->getUpdateChargeStatus($gateway);

        $handler = $this->getHandler($pendingTransactions);
        $handler->payments_charged_back($event);

        $this->assertEquals(Charge::SUCCEEDED, $charge->refresh()->status);
    }

    public function testPaymentPaidOut(): void
    {
        $event = [
            'id' => 'EV123',
            'created_at' => '2014-08-03T12:00:00.000Z',
            'action' => 'chargeback_settled',
            'resource_type' => 'paid_out',
            'links' => [
                'payment' => 'PM131',
                'organisation' => 'user_12345',
            ],
            'details' => [
                'origin' => 'gocardless',
                'cause' => 'payment_confirmed',
                'description' => 'Payment was confirmed as collected',
            ],
        ];

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->gateway = GoCardlessGateway::ID;
        $charge->gateway_id = 'PM131';
        $charge->amount = 100;
        $charge->currency = 'usd';
        $charge->status = Charge::PENDING;
        $charge->setPaymentSource(self::$bankAccount);
        $charge->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturn([Charge::SUCCEEDED, '']);
        $pendingTransactions = $this->getUpdateChargeStatus($gateway);

        $handler = $this->getHandler($pendingTransactions);
        $handler->payments_charged_back($event);

        $this->assertEquals(Charge::SUCCEEDED, $charge->refresh()->status);
    }
}
