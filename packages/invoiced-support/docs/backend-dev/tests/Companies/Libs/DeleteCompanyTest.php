<?php

namespace App\Tests\Companies\Libs;

use App\Companies\Libs\DeleteCompany;
use App\Companies\Models\CanceledCompany;
use App\Companies\Models\Company;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\DeleteCanceledCompanies;
use App\EntryPoint\CronJob\DeleteExpiredTrialCompanies;
use App\Tests\AppTestCase;
use App\Core\Utils\InfuseUtility as Utility;

class DeleteCompanyTest extends AppTestCase
{
    private static Company $company2;
    private static Company $company3;
    private static Company $company4;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::getService('test.tenant')->clear();

        self::$company2 = new Company();
        self::$company2->name = 'TEST-ABANDONED';
        self::$company2->username = 'testabandoned'.time();
        self::$company2->saveOrFail();
        BillingProfile::getOrCreate(self::$company2);
        $createdAt = Utility::unixToDb(time() - (86400 * 31));
        self::getService('test.database')->update('Companies', ['created_at' => $createdAt], ['id' => self::$company2->id()]);

        self::$company3 = new Company();
        self::$company3->name = 'TEST-CANCELED';
        self::$company3->email = 'test@example.com';
        self::$company3->username = 'testcanceled'.time();
        self::$company3->canceled = true;
        self::$company3->canceled_at = time() - (86400 * 91);
        self::$company3->saveOrFail();
        BillingProfile::getOrCreate(self::$company3);

        self::$company4 = new Company();
        self::$company4->name = 'TEST-EXPIRED-TRIAL';
        self::$company4->username = 'testexpiredtrial'.time();
        self::$company4->trial_ends = time() - (86400 * 91);
        self::$company4->saveOrFail();
        BillingProfile::getOrCreate(self::$company4);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$company2->delete();
        self::$company3->delete();
        self::$company4->delete();

        self::getService('test.database')->executeStatement('DELETE FROM CanceledCompanies');
    }

    public function testDeleteOldCanceledCompanies(): void
    {
        $job = new DeleteCanceledCompanies(new DeleteCompany(self::getService('test.database')));
        $job->setStatsd(new StatsdClient());

        $job->execute(new Run());

        $this->assertInstanceOf(Company::class, Company::find(self::$company->id()));
        $this->assertNull(Company::find(self::$company3->id()));
        $this->assertInstanceOf(Company::class, Company::find(self::$company4->id()));

        // should create a canceled company
        $canceledCompany = CanceledCompany::findOrFail(self::$company3->id());

        $expected = [
            'address1' => null,
            'address2' => null,
            'address_extra' => null,
            'canceled_at' => self::$company3->canceled_at,
            'canceled_reason' => '',
            'city' => null,
            'converted_at' => null,
            'converted_from' => '',
            'country' => null,
            'created_at' => self::$company3->created_at,
            'creator_id' => self::$company3->creator_id,
            'custom_domain' => null,
            'email' => 'test@example.com',
            'id' => self::$company3->id(),
            'name' => 'TEST-CANCELED',
            'past_due' => false,
            'postal_code' => null,
            'referred_by' => '',
            'state' => null,
            'stripe_customer' => '',
            'invoiced_customer' => '',
            'industry' => null,
            'tax_id' => null,
            'trial_started' => null,
            'type' => null,
            'updated_at' => self::$company3->updated_at,
            'username' => self::$company3->username,
            'billing_profile_id' => self::$company3->billing_profile_id,
        ];
        $this->assertEquals($expected, $canceledCompany->toArray());
    }

    public function testDeleteExpiredTrialCompanies(): void
    {
        $job = new DeleteExpiredTrialCompanies(new DeleteCompany(self::getService('test.database')));
        $job->setStatsd(new StatsdClient());

        $job->execute(new Run());

        $this->assertEquals(1, Company::where('id', self::$company->id())->count());

        $this->assertInstanceOf(Company::class, Company::find(self::$company->id()));
        $this->assertNull(Company::find(self::$company4->id()));
    }
}
