<?php

namespace App\Tests\Companies\Libs;

use App\Companies\Libs\NewCompanySignUp;
use App\Companies\Models\Company;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Entitlements\Models\Product;
use App\Tests\AppTestCase;

class NewCompanySignUpTest extends AppTestCase
{
    private function getClass(string $env = 'test'): NewCompanySignUp
    {
        return new NewCompanySignUp(self::getService('test.user_context'), $env, self::getService('test.company_entitlements_manager'));
    }

    public function testDetermineUsername(): void
    {
        $name = 'Invoiced, Inc.:/?!#';

        $signup = $this->getClass();
        $this->assertEquals('invoicedinc', $signup->determineUsername($name));

        self::$company = new Company();
        self::$company->username = 'invoicedinc';
        self::$company->saveOrFail();

        $username = $signup->determineUsername($name);
        $this->assertNotEquals('invoiced', $username);
        $this->assertStringStartsWith('invoiced', $username);
    }

    public function testEntitlementsReceivables(): void
    {
        $signup = $this->getClass();

        // test with invitation
        $this->assertEquals(new EntitlementsChangeset(
            products: [
                Product::where('name', 'Accounts Receivable Free')->one(),
            ],
            features: [
                'needs_onboarding' => true,
            ],
            quota: [
                'users' => 3,
                'aws_email_daily_limit' => 50,
            ],
        ), $signup->getEntitlements(false, true));

        // test without invitation
        $this->assertEquals(new EntitlementsChangeset(
            products: [
                Product::where('name', 'Free Trial')->one(),
            ],
            features: [
                'needs_onboarding' => true,
                'not_activated' => true,
            ],
            quota: [
                'users' => 10,
                'transactions_per_day' => 20,
                'aws_email_daily_limit' => 100,
            ],
        ), $signup->getEntitlements(false, false));
    }

    public function testEntitlementsPayables(): void
    {
        $signup = $this->getClass();

        // test with invitation
        $this->assertEquals(new EntitlementsChangeset(
            products: [
                Product::where('name', 'Accounts Payable Free')->one(),
            ],
            features: [
                'needs_onboarding' => true,
            ],
            quota: [
                'users' => 3,
                'aws_email_daily_limit' => 50,
            ],
        ), $signup->getEntitlements(true, true));

        // test without invitation
        $this->assertEquals(new EntitlementsChangeset(
            products: [
                Product::where('name', 'Free Trial')->one(),
            ],
            features: [
                'needs_onboarding' => true,
                'not_activated' => true,
            ],
            quota: [
                'users' => 10,
                'transactions_per_day' => 20,
                'aws_email_daily_limit' => 100,
            ],
        ), $signup->getEntitlements(true, false));
    }

    public function testEntitlementsReceivablesSandbox(): void
    {
        $signup = $this->getClass('sandbox');

        $this->assertEquals(new EntitlementsChangeset(
            products: [
                Product::where('name', 'Sandbox')->one(),
            ],
            features: [
                'needs_onboarding' => true,
            ],
            quota: [
                'aws_email_daily_limit' => 50,
            ],
        ), $signup->getEntitlements(false, false));
    }

    public function testEntitlementsPayablesSandbox(): void
    {
        $signup = $this->getClass('sandbox');

        $this->assertEquals(new EntitlementsChangeset(
            products: [
                Product::where('name', 'Sandbox')->one(),
            ],
            features: [
                'needs_onboarding' => true,
            ],
            quota: [
                'aws_email_daily_limit' => 50,
            ],
        ), $signup->getEntitlements(true, false));
    }
}
