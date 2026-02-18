<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Onboarding\PhoneVerificationFinishStep;
use App\Companies\Verification\PhoneVerification;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class PhoneVerificationFinishStepTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStep(): PhoneVerificationFinishStep
    {
        return self::getService('test.onboarding_phone_verification_finish');
    }

    public function testCanRevisitAndMustPerform(): void
    {
        $step = $this->getStep();
        $this->assertFalse($step->canRevisit(self::$company));
        $this->assertFalse($step->mustPerform(self::$company));

        $companyPhone = new CompanyPhoneNumber();
        $companyPhone->phone = '+123456789';
        $companyPhone->channel = PhoneVerificationChannel::Sms;
        $companyPhone->saveOrFail();
        $this->assertTrue($step->canRevisit(self::$company));
        $this->assertTrue($step->mustPerform(self::$company));

        $companyPhone->verified_at = CarbonImmutable::now();
        $companyPhone->saveOrFail();
        $this->assertFalse($step->canRevisit(self::$company));
        $this->assertFalse($step->mustPerform(self::$company));
    }

    public function testHandleSubmit(): void
    {
        $step = $this->getStep();
        $verification = Mockery::mock(PhoneVerification::class);
        $verification->shouldReceive('complete')->once();
        $step->setPhoneVerification($verification);

        $companyPhone = new CompanyPhoneNumber();
        $companyPhone->phone = '+12345678900';
        $companyPhone->channel = PhoneVerificationChannel::Call;
        $companyPhone->saveOrFail();
        $request = new Request([], [
            'code' => '1234',
        ]);
        $step->handleSubmit(self::$company, $request);
    }
}
