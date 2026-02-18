<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Models\Company;
use App\Companies\Onboarding\BusinessTypeStep;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class BusinessTypeStepTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStep(): BusinessTypeStep
    {
        return self::getService('test.onboarding_business_type');
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
        $company->type = '';
        $this->assertTrue($step->mustPerform($company));

        $company->type = 'company';
        $this->assertFalse($step->mustPerform($company));
    }

    public function testHandleSubmit(): void
    {
        $step = $this->getStep();

        $step->handleSubmit(self::$company, new Request([], ['entity_type' => 'person']));
        $this->assertEquals('person', self::$company->type);
    }
}
