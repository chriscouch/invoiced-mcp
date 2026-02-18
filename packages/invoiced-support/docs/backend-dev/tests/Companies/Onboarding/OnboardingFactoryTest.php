<?php

namespace App\Tests\Companies\Onboarding;

use App\Companies\Enums\OnboardingStepType;
use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Onboarding\BusinessTypeStep;
use App\Companies\Onboarding\CompanyInfoStep;
use App\Companies\Onboarding\EmailVerificationStep;
use App\Companies\Onboarding\OnboardingFactory;
use App\Companies\Onboarding\PhoneVerificationFinishStep;
use App\Companies\Onboarding\PhoneVerificationStartStep;
use App\Companies\Onboarding\TaxIdStep;
use App\Companies\ValueObjects\OnboardingState;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class OnboardingFactoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getFactory(): OnboardingFactory
    {
        return self::getService('test.onboarding_factory');
    }

    public function testBuildStateNoCurrentStep(): void
    {
        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, null);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::VerifyEmail,
            previousStep: null,
            nextStep: OnboardingStepType::VerifyPhoneStart,
        ), $state);
    }

    public function testBuildStateVerifyEmail(): void
    {
        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, OnboardingStepType::VerifyEmail);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::VerifyEmail,
            previousStep: null,
            nextStep: OnboardingStepType::VerifyPhoneStart,
        ), $state);
        $this->assertEquals(2, $state->getCurrentStepNumber());
        $this->assertEquals(7, $state->getTotalSteps());
    }

    public function testBuildStateVerifyPhoneStart(): void
    {
        $companyEmail = CompanyEmailAddress::where('email', self::$company->email)->one();
        $companyEmail->verified_at = CarbonImmutable::now();
        $companyEmail->saveOrFail();

        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, OnboardingStepType::VerifyPhoneStart);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::VerifyPhoneStart,
            previousStep: null,
            nextStep: OnboardingStepType::BusinessType,
        ), $state);
        $this->assertEquals(3, $state->getCurrentStepNumber());
        $this->assertEquals(7, $state->getTotalSteps());
    }

    public function testBuildStateVerifyPhoneFinish(): void
    {
        $companyPhone = new CompanyPhoneNumber();
        $companyPhone->phone = '+121345678';
        $companyPhone->channel = PhoneVerificationChannel::Sms;
        $companyPhone->saveOrFail();

        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, OnboardingStepType::VerifyPhoneFinish);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::VerifyPhoneFinish,
            previousStep: OnboardingStepType::VerifyPhoneStart,
            nextStep: OnboardingStepType::BusinessType,
        ), $state);
        $this->assertEquals(4, $state->getCurrentStepNumber());
        $this->assertEquals(7, $state->getTotalSteps());
    }

    public function testBuildStateBusinessType(): void
    {
        $companyPhone = CompanyPhoneNumber::one();
        $companyPhone->verified_at = CarbonImmutable::now();
        $companyPhone->saveOrFail();

        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, OnboardingStepType::BusinessType);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::BusinessType,
            previousStep: null,
            nextStep: OnboardingStepType::CompanyInfo,
        ), $state);
        $this->assertEquals(5, $state->getCurrentStepNumber());
        $this->assertEquals(7, $state->getTotalSteps());
    }

    public function testBuildStateCompanyInfo(): void
    {
        self::$company->type = 'company';
        self::$company->saveOrFail();

        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, OnboardingStepType::CompanyInfo);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::CompanyInfo,
            previousStep: OnboardingStepType::BusinessType,
//            nextStep: OnboardingStepType::TaxId,
            nextStep: null,
        ), $state);
        $this->assertEquals(6, $state->getCurrentStepNumber());
        $this->assertEquals(7, $state->getTotalSteps());
    }

    public function testBuildStateTaxId(): void
    {
        self::$company->name = 'TEST';
        self::$company->address1 = '1234 Main St';
        self::$company->address2 = 'Suite 1';
        self::$company->city = 'Austin';
        self::$company->state = 'TX';
        self::$company->postal_code = '78701';
        self::$company->country = 'US';
        self::$company->industry = 'Software';
        self::$company->phone = '1234567';
        self::$company->test_mode = false;
        self::$company->saveOrFail();

        $factory = $this->getFactory();
        $state = $factory->buildState(self::$company, OnboardingStepType::TaxId);
        $this->assertEquals(new OnboardingState(
            allSteps: [
                OnboardingStepType::VerifyEmail,
                OnboardingStepType::VerifyPhoneStart,
                OnboardingStepType::VerifyPhoneFinish,
                OnboardingStepType::BusinessType,
                OnboardingStepType::CompanyInfo,
                OnboardingStepType::TaxId,
            ],
            currentStep: OnboardingStepType::TaxId,
            previousStep: OnboardingStepType::CompanyInfo,
            nextStep: null,
        ), $state);
        $this->assertEquals(7, $state->getCurrentStepNumber());
        $this->assertEquals(7, $state->getTotalSteps());
    }

    public function testGet(): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(EmailVerificationStep::class, $factory->get(OnboardingStepType::VerifyEmail));
        $this->assertInstanceOf(PhoneVerificationStartStep::class, $factory->get(OnboardingStepType::VerifyPhoneStart));
        $this->assertInstanceOf(PhoneVerificationFinishStep::class, $factory->get(OnboardingStepType::VerifyPhoneFinish));
        $this->assertInstanceOf(BusinessTypeStep::class, $factory->get(OnboardingStepType::BusinessType));
        $this->assertInstanceOf(CompanyInfoStep::class, $factory->get(OnboardingStepType::CompanyInfo));
        $this->assertInstanceOf(TaxIdStep::class, $factory->get(OnboardingStepType::TaxId));
    }
}
