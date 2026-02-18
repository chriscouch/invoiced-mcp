<?php

namespace App\Tests\Reports\Dashboard;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Mailer\Mailer;
use App\Core\Utils\ValueObjects\Interval;
use App\Reports\Dashboard\EmailMemberUpdate;
use App\Tests\AppTestCase;
use Mockery;

class EmailMemberUpdateTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getUpdate(Company $company): EmailMemberUpdate
    {
        $update = self::getService('test.email_update');
        $update->setContext($company, Member::oneOrNull(), 'month');

        return $update;
    }

    public function testCannotSend(): void
    {
        $company = new Company();
        $company->canceled = true;
        $update = $this->getUpdate($company);
        $this->assertFalse($update->send());

        // inactive subscription
        $company->email = 'test@example.com';
        $this->assertFalse($update->send());
    }

    public function testSend(): void
    {
        $member1 = $this->createMember('test@day.com', Interval::DAY, Member::OWNER_RESTRICTION);
        $member2 = $this->createMember('test@week.com', Interval::WEEK, Member::OWNER_RESTRICTION);
        $member3 = $this->createMember('test@month.com', Interval::MONTH, Member::UNRESTRICTED);

        // prepare data for permission check
        $customer1 = new Customer();
        $customer1->name = 'Customer 1';
        $customer1->country = 'US';
        $customer1->owner = $member1->user();
        $customer1->saveOrFail();
        $invoice1 = new Invoice();
        $invoice1->setCustomer($customer1);
        $invoice1->items = [
            [
                'unit_cost' => 200,
            ],
        ];
        $invoice1->saveOrFail();

        $customer2 = new Customer();
        $customer2->owner = $member2->user();
        $customer2->name = 'Customer 2';
        $customer2->country = 'US';
        $customer2->saveOrFail();
        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer2);
        $invoice2->items = [
            [
                'unit_cost' => 100,
            ],
        ];
        $invoice2->saveOrFail();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('sendToUser');
        $emailUpdate = $this->getUpdate(self::$company);
        $emailUpdate->setMailer($mailer);
        $emailUpdate->setContext(self::$company, $member1, Interval::DAY);
        [$frequency, $start, $end] = $emailUpdate->getPeriod();
        $this->assertEquals([$frequency, $start, $end], ['1 day', strtotime('-1 day'), time()]);
        $built = $emailUpdate->buildVariables($frequency, $start, $end);
        $this->assertEquals('$200.00', $built['totalOutstanding']);
        $this->assertTrue($emailUpdate->send());

        $emailUpdate->setContext(self::$company, $member2, Interval::WEEK);

        [$frequency, $start, $end] = $emailUpdate->getPeriod();
        $this->assertEquals([$frequency, $start, $end], ['1 week', strtotime('-1 week'), time()]);
        $built = $emailUpdate->buildVariables($frequency, $start, $end);
        $this->assertEquals('$100.00', $built['totalOutstanding']);
        $this->assertTrue($emailUpdate->send());

        $emailUpdate->setContext(self::$company, $member3, Interval::MONTH);

        [$frequency, $start, $end] = $emailUpdate->getPeriod();
        $this->assertEquals([$frequency, $start, $end], ['1 month', strtotime('-1 month'), time()]);
        $built = $emailUpdate->buildVariables($frequency, $start, $end);
        $this->assertEquals('$300.00', $built['totalOutstanding']);
        $this->assertTrue($emailUpdate->send());
    }

    private function createMember(string $email, string $period, string $restriction): Member
    {
        self::getService('test.database')->delete('Users', ['email' => $email]);

        $user = new User();
        $user->email = $email;
        $user->first_name = 'Test';
        $user->password = ['GdZMwwCiW[JTM89', 'GdZMwwCiW[JTM89']; /* @phpstan-ignore-line */
        $user->ip = '127.0.0.1';
        $user->saveOrFail();
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->user_id = (int) $user->id();
        $member->tenant_id = self::$company->id;
        $member->email_update_frequency = $period;
        $member->skipMemberCheck();
        $member->restriction_mode = $restriction;
        $member->saveOrFail();

        return $member;
    }
}
