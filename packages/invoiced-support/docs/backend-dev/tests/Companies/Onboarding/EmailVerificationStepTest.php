<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Onboarding\EmailVerificationStep;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Request;

class EmailVerificationStepTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStep(): EmailVerificationStep
    {
        return self::getService('test.onboarding_email_verification');
    }

    public function testCanRevisitAndMustPerform(): void
    {
        $step = $this->getStep();
        $this->assertTrue($step->canRevisit(self::$company));
        $this->assertTrue($step->mustPerform(self::$company));

        $companyEmail = CompanyEmailAddress::where('email', self::$company->email)->one();
        $companyEmail->verified_at = CarbonImmutable::now();
        $companyEmail->saveOrFail();
        $this->assertFalse($step->canRevisit(self::$company));
        $this->assertFalse($step->mustPerform(self::$company));
    }

    public function testHandleSubmit(): void
    {
        $step = $this->getStep();
        $request = new Request([], [
            'email' => self::$company->email,
            'code' => CompanyEmailAddress::one()->code,
        ]);
        $step->handleSubmit(self::$company, $request);

        $companyEmail = CompanyEmailAddress::where('email', self::$company->email)->oneOrNull();
        $this->assertInstanceOf(CompanyEmailAddress::class, $companyEmail);
        $this->assertNotNull($companyEmail->verified_at);
    }
}
