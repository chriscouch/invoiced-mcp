<?php

namespace App\Tests\PaymentProcessing\Jobs;

use App\Core\Cron\ValueObjects\Run;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\Tests\AppTestCase;

class SendAchVerificationRemindersTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasBankAccount(StripeGateway::ID);
        self::$bankAccount->verified = false;
        self::$bankAccount->verification_last_sent = strtotime('-3 days');
        self::$bankAccount->save();
        self::$customer->setDefaultPaymentSource(self::$bankAccount);
    }

    public function testGetBankAccounts(): void
    {
        $job = self::getService('test.send_ach_verification_reminders');
        $accounts = $job->getBankAccounts()->all();

        $this->assertCount(1, $accounts);
        $this->assertEquals(self::$bankAccount->id(), $accounts[0]->id());
    }

    public function testSendReminders(): void
    {
        $job = self::getService('test.send_ach_verification_reminders');
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());

        $this->assertGreaterThan(strtotime('-3 days'), self::$bankAccount->refresh()->verification_last_sent);
    }
}
