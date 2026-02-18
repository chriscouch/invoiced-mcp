<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Enums\TaxIdType;
use App\Companies\Models\CompanyTaxId;
use App\Companies\Onboarding\TaxIdStep;
use App\Companies\Verification\UsTaxIdVerification;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class TaxIdStepTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStep(): TaxIdStep
    {
        return self::getService('test.onboarding_tax_id');
    }

    public function testCanRevisit(): void
    {
        $step = $this->getStep();

        self::$company->features->enable('accounts_receivable');
        self::$company->country = 'US';
        self::$company->test_mode = false;
        $this->assertFalse($step->canRevisit(self::$company));

        self::$company->test_mode = true;
        $this->assertFalse($step->canRevisit(self::$company));

        self::$company->test_mode = false;
        self::$company->country = 'CA';
        $this->assertFalse($step->canRevisit(self::$company));

        self::$company->country = 'US';
        self::$company->features->disable('accounts_receivable');
        $this->assertFalse($step->canRevisit(self::$company));
    }

    public function testMustPerform(): void
    {
        $step = $this->getStep();

        self::$company->features->enable('accounts_receivable');
        self::$company->country = 'US';
        self::$company->test_mode = false;
        $this->assertFalse($step->mustPerform(self::$company));

        self::$company->test_mode = true;
        $this->assertFalse($step->mustPerform(self::$company));

        self::$company->test_mode = false;
        self::$company->country = 'CA';
        $this->assertFalse($step->mustPerform(self::$company));

        self::$company->country = 'US';
        self::$company->features->disable('accounts_receivable');
        $this->assertFalse($step->mustPerform(self::$company));
    }

    public function testHandleSubmitEin(): void
    {
        $taxIdVerification = Mockery::mock(UsTaxIdVerification::class);
        $taxIdVerification->shouldReceive('verify')
            ->withArgs([self::$company->id, 'TEST', '1234', true])
            ->once()
            ->andReturn(7);
        $step = new TaxIdStep($taxIdVerification, 'test');

        $step->handleSubmit(self::$company, new Request([], ['ein' => '1234']));

        $taxId = CompanyTaxId::queryWithTenant(self::$company)
            ->sort('id DESC')
            ->oneOrNull();
        $this->assertInstanceOf(CompanyTaxId::class, $taxId);
        $this->assertEquals('TEST', $taxId->name);
        $this->assertEquals('1234', $taxId->tax_id);
        $this->assertEquals(TaxIdType::EIN, $taxId->tax_id_type);
        $this->assertEquals('US', $taxId->country);
        $this->assertEquals(7, $taxId->irs_code);
        $this->assertNotNull($taxId->verified_at);
    }

    public function testHandleSubmitFake(): void
    {
        $taxIdVerification = Mockery::mock(UsTaxIdVerification::class);
        $taxIdVerification->shouldNotReceive('verify')->once();

        $step = new TaxIdStep($taxIdVerification, 'production');
        $step->handleSubmit(self::$company, new Request([], ['ein' => '123456789']));

        $step = new TaxIdStep($taxIdVerification, 'test');
        $step->handleSubmit(self::$company, new Request([], ['ein' => '123456789']));

        $taxId = CompanyTaxId::queryWithTenant(self::$company)
            ->sort('id DESC')
            ->oneOrNull();
        $this->assertInstanceOf(CompanyTaxId::class, $taxId);
        $this->assertEquals('TEST', $taxId->name);
        $this->assertEquals('123456789', $taxId->tax_id);
        $this->assertEquals(TaxIdType::EIN, $taxId->tax_id_type);
        $this->assertEquals('US', $taxId->country);
        $this->assertNull($taxId->irs_code);
        $this->assertNotNull($taxId->verified_at);
    }

    public function testHandleSubmitSsn(): void
    {
        $taxIdVerification = Mockery::mock(UsTaxIdVerification::class);
        $taxIdVerification->shouldReceive('verify')
            ->withArgs([self::$company->id, 'TEST', '1234', false])
            ->once()
            ->andReturn(6);
        $step = new TaxIdStep($taxIdVerification, 'test');

        $step->handleSubmit(self::$company, new Request([], ['ssn' => '1234']));

        $taxId = CompanyTaxId::queryWithTenant(self::$company)
            ->sort('id DESC')
            ->oneOrNull();
        $this->assertInstanceOf(CompanyTaxId::class, $taxId);
        $this->assertEquals('1234', $taxId->tax_id);
        $this->assertEquals(TaxIdType::SSN, $taxId->tax_id_type);
        $this->assertEquals('US', $taxId->country);
        $this->assertEquals(6, $taxId->irs_code);
        $this->assertNotNull($taxId->verified_at);
    }
}
