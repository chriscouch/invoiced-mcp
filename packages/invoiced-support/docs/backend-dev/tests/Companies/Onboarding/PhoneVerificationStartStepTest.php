<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Onboarding\PhoneVerificationStartStep;
use App\Companies\Verification\PhoneVerification;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class PhoneVerificationStartStepTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getStep(): PhoneVerificationStartStep
    {
        return self::getService('test.onboarding_phone_verification_start');
    }

    public function testCanRevisitAndMustPerform(): void
    {
        $step = $this->getStep();
        $this->assertTrue($step->canRevisit(self::$company));
        $this->assertTrue($step->mustPerform(self::$company));

        $companyPhone = new CompanyPhoneNumber();
        $companyPhone->phone = '+123456789';
        $companyPhone->channel = PhoneVerificationChannel::Sms;
        $companyPhone->verified_at = CarbonImmutable::now();
        $companyPhone->saveOrFail();
        $this->assertFalse($step->canRevisit(self::$company));
        $this->assertFalse($step->mustPerform(self::$company));
    }

    public function testHandleSubmit(): void
    {
        $step = $this->getStep();
        $verification = Mockery::mock(PhoneVerification::class);
        $verification->shouldReceive('start')->once();
        $step->setPhoneVerification($verification);

        $request = new Request([], [
            'country_code' => '1',
            'phone' => '2345678900',
            'channel' => PhoneVerificationChannel::Call->value,
        ]);
        $step->handleSubmit(self::$company, $request);
    }
}
