<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Models\Company;
use App\Companies\Models\CompanyAddress;
use App\Companies\Onboarding\CompanyInfoStep;
use App\Companies\Verification\AddressVerification;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class CompanyInfoStepTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStep(): CompanyInfoStep
    {
        return self::getService('test.onboarding_company_info');
    }

    public function testCanRevisit(): void
    {
        $step = $this->getStep();
        $company = new Company();
        $this->assertTrue($step->canRevisit($company));
    }

    public function testMustPerform(): void
    {
        $step = $this->getStep();
        $company = new Company();
        $this->assertTrue($step->mustPerform($company));

        $company->name = 'Invoiced, Inc.';
        $this->assertTrue($step->mustPerform($company));

        $company->address1 = '1234 main st';
        $this->assertTrue($step->mustPerform($company));

        $company->country = 'US';
        $this->assertTrue($step->mustPerform($company));

        $company->industry = 'Software';
        $this->assertFalse($step->mustPerform($company));
    }

    public function testHandleSubmit(): void
    {
        $addressVerification = Mockery::mock(AddressVerification::class);
        $addressVerification->shouldReceive('countryIsSupported')->andReturn(true);
        $addressVerification->shouldReceive('validate')->once();
        $step = new CompanyInfoStep($addressVerification);
        $request = new Request([], [
            'company_name' => 'Handle Submit',
            'has_dba' => '1',
            'nickname' => 'DBA Name',
            'address1' => '1234 Main St',
            'address2' => 'Suite 1',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
            'industry' => 'Software',
        ]);
        $step->handleSubmit(self::$company, $request);

        $this->assertEquals('Handle Submit', self::$company->name);
        $this->assertEquals('DBA Name', self::$company->nickname);
        $this->assertEquals('1234 Main St', self::$company->address1);
        $this->assertEquals('Suite 1', self::$company->address2);
        $this->assertEquals('Austin', self::$company->city);
        $this->assertEquals('TX', self::$company->state);
        $this->assertEquals('78701', self::$company->postal_code);
        $this->assertEquals('US', self::$company->country);
        $this->assertEquals('Software', self::$company->industry);

        $companyAddress = CompanyAddress::queryWithTenant(self::$company)->oneOrNull();
        $this->assertInstanceOf(CompanyAddress::class, $companyAddress);
        $this->assertEquals('1234 Main St', $companyAddress->address1);
        $this->assertEquals('Suite 1', $companyAddress->address2);
        $this->assertEquals('Austin', $companyAddress->city);
        $this->assertEquals('TX', $companyAddress->state);
        $this->assertEquals('78701', $companyAddress->postal_code);
        $this->assertEquals('US', $companyAddress->country);
        $this->assertNotNull($companyAddress->verified_at);
    }
}
