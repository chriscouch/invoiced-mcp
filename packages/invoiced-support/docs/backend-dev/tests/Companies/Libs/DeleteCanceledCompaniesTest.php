<?php

namespace App\Tests\Companies\Libs;

use App\Companies\Libs\DeleteCompany;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\DeleteCanceledCompanies;
use App\AccountsReceivable\Models\Invoice;
use App\Tests\AppTestCase;

class DeleteCanceledCompaniesTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testRun(): void
    {
        self::hasCustomer();
        self::hasInvoice();
        self::hasPayment(self::$customer);
        self::hasInvoice();
        self::hasCreditNote();
        self::hasCard();
        self::hasIntacctAccount();
        self::hasQuickBooksAccount();
        self::hasUnappliedCreditNote();
        self::hasMerchantAccount('test');
        self::hasAvalaraAccount();
        self::hasBankAccount();
        self::hasCoupon();
        self::hasCustomField();
        self::hasEstimate();
        self::hasFile();
        self::hasInactiveCustomer();
        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail();
        self::hasItem();
        self::hasLateFeeSchedule();
        self::hasNetSuiteAccount();
        self::hasPlan();
        self::hasInvoice();
        self::hasTransaction();
        self::hasRefund();
        self::hasSlackAccount();
        self::hasSmtpAccount();
        self::hasSubscription();
        self::hasTaxRate();
        self::hasTwilioAccount();
        self::hasXeroAccount();

        $job = new DeleteCanceledCompanies(new DeleteCompany(self::getService('test.database')));
        $job->setStatsd(new StatsdClient());

        $this->assertEquals(4, Invoice::query()->count());
        $job->execute(new Run());
        $this->assertEquals(4, Invoice::query()->count());

        self::$company->canceled_at = 1;
        self::$company->canceled = true;
        self::$company->saveOrFail();
        $job->execute(new Run());
        $this->assertEquals(0, Invoice::query()->count());
    }
}
