<?php

namespace App\Tests\Reports\Dashboard;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\AgingReport;
use App\Reports\ValueObjects\AgingBreakdown;
use App\Tests\AppTestCase;

class AgingReportTest extends AppTestCase
{
    private static Customer $customer2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::hasCustomer();

        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->date = time();
        $invoice1->items = [['unit_cost' => 1]];
        $invoice1->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->date = strtotime('-7 days');
        $invoice2->items = [['unit_cost' => 2]];
        $invoice2->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->setCustomer(self::$customer);
        $invoice3->date = strtotime('-14 days');
        $invoice3->due_date = strtotime('+7 days');
        $invoice3->items = [['unit_cost' => 3]];
        $invoice3->saveOrFail();

        $invoice4 = new Invoice();
        $invoice4->setCustomer(self::$customer);
        $invoice4->date = strtotime('-30 days');
        $invoice4->due_date = strtotime('-7 days');
        $invoice4->items = [['unit_cost' => 4]];
        $invoice4->saveOrFail();

        $invoice5 = new Invoice();
        $invoice5->setCustomer(self::$customer);
        $invoice5->date = strtotime('-45 days');
        $invoice5->due_date = strtotime('-45 days');
        $invoice5->items = [['unit_cost' => 5]];
        $invoice5->saveOrFail();

        $invoice6 = new Invoice();
        $invoice6->setCustomer(self::$customer);
        $invoice6->date = strtotime('-60 days');
        $invoice6->due_date = strtotime('-60 days');
        $invoice6->items = [['unit_cost' => 6]];
        $invoice6->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->date = strtotime('-60 days');
        $creditNote->items = [['unit_cost' => 3]];
        $creditNote->saveOrFail();

        $customer2 = new Customer();
        $customer2->name = 'Test';
        $customer2->country = 'US';
        $customer2->saveOrFail();
        self::$customer2 = $customer2;

        $invoice7 = new Invoice();
        $invoice7->setCustomer($customer2);
        $invoice7->date = strtotime('-15 days');
        $invoice7->items = [['unit_cost' => 100]];
        $invoice7->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        self::hasCustomField('customer');
    }

    private function getReport(): AgingReport
    {
        $agingBreakdown = new AgingBreakdown([0, 7, 14, 30, 60], 'date');

        return new AgingReport($agingBreakdown, self::$company, self::getService('test.database'));
    }

    private function getReportDueDate(): AgingReport
    {
        $agingBreakdown = new AgingBreakdown([-1, 0, 30, 60], 'due_date');

        return new AgingReport($agingBreakdown, self::$company, self::getService('test.database'));
    }

    public function testBuildForCompany(): void
    {
        $report = $this->getReport();
        $expected = [
            [
                'amount' => new Money('usd', 100),
                'count' => 1,
            ],
            [
                'amount' => new Money('usd', 200),
                'count' => 1,
            ],
            [
                'amount' => new Money('usd', 10300),
                'count' => 2,
            ],
            [
                'amount' => new Money('usd', 900),
                'count' => 2,
            ],
            [
                'amount' => new Money('usd', 300),
                'count' => 2,
            ],
        ];

        $this->assertEquals($expected, $report->buildForCompany('usd'));
    }

    public function testBuildForCompanyDueDate(): void
    {
        $report = $this->getReportDueDate();
        $expected = [
            [
                'amount' => new Money('usd', 10300),
                'count' => 5,
            ],
            [
                'amount' => new Money('usd', 400),
                'count' => 1,
            ],
            [
                'amount' => new Money('usd', 500),
                'count' => 1,
            ],
            [
                'amount' => new Money('usd', 600),
                'count' => 1,
            ],
        ];

        $this->assertEquals($expected, $report->buildForCompany('usd'));
    }

    public function testBuildForCustomer(): void
    {
        $report = $this->getReport();
        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('usd', 100),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 200),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 300),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 900),
                    'count' => 2,
                ],
                [
                    'amount' => new Money('usd', 300),
                    'count' => 2,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomer((int) self::$customer->id(), 'usd'));

        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomer((int) self::$customer->id(), 'eur'));
    }

    public function testBuildForCustomerByDueDate(): void
    {
        $report = $this->getReportDueDate();
        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('usd', 300),
                    'count' => 4,
                ],
                [
                    'amount' => new Money('usd', 400),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 500),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 600),
                    'count' => 1,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomer((int) self::$customer->id(), 'usd'));

        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('eur', 0),
                    'count' => 0,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomer((int) self::$customer->id(), 'eur'));
    }

    public function testBuildForCustomers(): void
    {
        $report = $this->getReport();
        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('usd', 100),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 200),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 300),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 900),
                    'count' => 2,
                ],
                [
                    'amount' => new Money('usd', 300),
                    'count' => 2,
                ],
            ],
            self::$customer2->id() => [
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 10000),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomers([self::$customer->id(), self::$customer2->id()], 'usd'));
    }

    public function testBuildForCustomersNoCurrency(): void
    {
        $report = $this->getReport();
        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('usd', 100),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 200),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 300),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 900),
                    'count' => 2,
                ],
                [
                    'amount' => new Money('usd', 300),
                    'count' => 2,
                ],
            ],
            self::$customer2->id() => [
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 10000),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomers([self::$customer->id(), self::$customer2->id()]));
    }

    public function testBuildForCustomersByDueDate(): void
    {
        $report = $this->getReportDueDate();
        $expected = [
            self::$customer->id() => [
                [
                    'amount' => new Money('usd', 300),
                    'count' => 4,
                ],
                [
                    'amount' => new Money('usd', 400),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 500),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 600),
                    'count' => 1,
                ],
            ],
            self::$customer2->id() => [
                [
                    'amount' => new Money('usd', 10000),
                    'count' => 1,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
                [
                    'amount' => new Money('usd', 0),
                    'count' => 0,
                ],
            ],
        ];
        $this->assertEquals($expected, $report->buildForCustomers([self::$customer->id(), self::$customer2->id()], 'usd'));
    }

    public function testBuildForCompanyOwnerRestriction(): void
    {
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser($this->createUser());
        $member->restriction_mode = Member::OWNER_RESTRICTION;
        $member->saveOrFail();

        $report = $this->getReport();
        $report->setMember($member);
        $expected = [
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
        ];

        $this->assertEquals($expected, $report->buildForCompany('usd'));
    }

    public function testBuildForCompanyCustomerRestriction(): void
    {
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser($this->createUser());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = [self::$customField->id => ['Texas']];
        $member->saveOrFail();

        $report = $this->getReport();
        $report->setMember($member);
        $expected = [
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
            [
                'amount' => new Money('usd', 0),
                'count' => 0,
            ],
        ];

        $this->assertEquals($expected, $report->buildForCompany('usd'));
    }

    private function createUser(): User
    {
        $newUser = new User();
        $newUser->email = uniqid().'@example.com';
        $newUser->password = ['gg7WEZ}cgN4FyFk', 'gg7WEZ}cgN4FyFk']; /* @phpstan-ignore-line */
        $newUser->first_name = 'John';
        $newUser->ip = '127.0.0.1';
        $newUser->saveOrFail();

        return $newUser;
    }
}
