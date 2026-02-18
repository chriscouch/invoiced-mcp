<?php

namespace App\Tests\Integrations\Adyen;

use App\EntryPoint\CronJob\DisputesResolutionCronJob;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class DisputesResolutionCronJobTest extends AppTestCase
{
    private DisputesResolutionCronJob $cronJob;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::acceptsCreditCards();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $tenantContext = self::getService('test.tenant');
        $this->cronJob = new DisputesResolutionCronJob($tenantContext);
    }

    public function testGetTasksReturnsDisputesWithCorrectCriteria(): void
    {
        // Create test disputes
        $this->createTestDispute(DisputeStatus::Pending, 10);
        $this->createTestDispute(DisputeStatus::Unresponded, 15);
        $this->createTestDispute(DisputeStatus::Won, 10); // Should not be included

        $tasks = $this->cronJob->getTasks();
        $tasksArray = iterator_to_array($tasks);

        $this->assertGreaterThanOrEqual(2, count($tasksArray));

        foreach ($tasksArray as $dispute) {
            $this->assertInstanceOf(Dispute::class, $dispute);
            $this->assertTrue(
                $dispute->status === DisputeStatus::Pending ||
                $dispute->status === DisputeStatus::Unresponded
            );
        }
    }

    public function testNotEnoughTimeElapsed(): void
    {
        $charge = $this->createTestCharge();
        $this->createTestCard($charge, 'visa');
        $dispute = $this->createTestDispute(DisputeStatus::Pending, 30, $charge); // Less than 60 days for visa

        $result = $this->cronJob->runTask($dispute);

        $this->assertTrue($result);

        $dispute->refresh();
        $this->assertEquals(DisputeStatus::Pending, $dispute->status); // Status unchanged
    }

    public function testCardNotFound(): void
    {
        $charge = $this->createTestCharge();
        // Don't create a card for this charge
        $dispute = $this->createTestDispute(DisputeStatus::Pending, 65, $charge);

        $result = $this->cronJob->runTask($dispute);

        $this->assertTrue($result);

        $dispute->refresh();
        $this->assertEquals(DisputeStatus::Pending, $dispute->status); // Status unchanged
    }

    public function testPendingDisputeAndVisaCard(): void
    {
        $charge = $this->createTestCharge();
        $this->createTestCard($charge, 'visa');
        $dispute = $this->createTestDispute(DisputeStatus::Pending, 65, $charge);

        $result = $this->cronJob->runTask($dispute);

        $this->assertTrue($result);

        $dispute->refresh();
        $this->assertEquals(DisputeStatus::Won, $dispute->status);
    }

    public function testUnrespondedDisputeAndMastercardCard(): void
    {
        $charge = $this->createTestCharge();
        $this->createTestCard($charge, 'mastercard');
        $dispute = $this->createTestDispute(DisputeStatus::Unresponded, 45, $charge);

        $result = $this->cronJob->runTask($dispute);

        $this->assertTrue($result);

        $dispute->refresh();
        $this->assertEquals(DisputeStatus::Lost, $dispute->status);
    }

    public function testDisputesWithDifferentCardBrands(): void
    {
        $testCases = [
            ['amex', DisputeStatus::Pending, 55, DisputeStatus::Won],
            ['amex', DisputeStatus::Pending, 45, DisputeStatus::Pending], // Not enough time
            ['diners', DisputeStatus::Unresponded, 30, DisputeStatus::Lost],
            ['discover', DisputeStatus::Unresponded, 20, DisputeStatus::Unresponded], // Not enough time
        ];

        foreach ($testCases as [$brand, $initialStatus, $daysElapsed, $expectedStatus]) {
            $charge = $this->createTestCharge();
            $this->createTestCard($charge, $brand);
            $dispute = $this->createTestDispute($initialStatus, $daysElapsed, $charge);

            $result = $this->cronJob->runTask($dispute);

            $this->assertTrue($result);

            $dispute->refresh();
            $this->assertEquals($expectedStatus, $dispute->status,
                "Failed for brand: $brand, initial: {$initialStatus->value}, days: $daysElapsed");
        }
    }

    public function testGetLockTtlReturns1800Seconds(): void
    {
        $lockTtl = DisputesResolutionCronJob::getLockTtl();
        $this->assertEquals(1800, $lockTtl);
    }

    private function createTestCharge(): Charge
    {
        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 1000;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = 'test_gateway';
        $charge->gateway_id = 'test_' . uniqid();
        $charge->payment_source_type = 'card';
        $charge->saveOrFail();

        return $charge;
    }

    private function createTestCard(Charge $charge, string $brand): Card
    {
        $card = new Card();
        $card->id = $charge->payment_source_id ?? rand(1000, 9999);
        $card->brand = $brand;
        $card->last4 = '4242';
        $card->exp_month = 12;
        $card->exp_year = 2025;
        $card->customer = self::$customer;
        $card->saveOrFail();

        // Update charge to reference this card
        $charge->payment_source_id = $card->id;
        $charge->saveOrFail();

        return $card;
    }

    private function createTestDispute(DisputeStatus $status, int $daysAgo, ?Charge $charge = null): Dispute
    {
        if (!$charge) {
            $charge = $this->createTestCharge();
        }

        $dispute = new Dispute();
        $dispute->charge = $charge;
        $dispute->currency = 'usd';
        $dispute->amount = $charge->amount;
        $dispute->gateway = $charge->gateway;
        $dispute->gateway_id = 'dispute_' . uniqid();
        $dispute->status = $status;
        $dispute->reason = 'Fraudulent';
        $dispute->saveOrFail();

        $targetTimestamp = CarbonImmutable::now()->subDays($daysAgo)->format('Y-m-d H:i:s');

        $connection = self::getService('test.database');
        $connection->executeUpdate(
            'UPDATE Disputes SET updated_at = ? WHERE id = ?',
            [$targetTimestamp, $dispute->id]
        );

        // Refresh the model to get the updated timestamp
        $dispute->refresh();

        return $dispute;
    }
}