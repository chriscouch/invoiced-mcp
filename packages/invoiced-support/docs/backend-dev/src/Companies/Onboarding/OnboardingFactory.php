<?php

namespace App\Companies\Onboarding;

use App\Companies\Enums\OnboardingStepType;
use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Companies\ValueObjects\OnboardingState;

class OnboardingFactory
{
    public function __construct(
        private EmailVerificationStep $emailVerificationStep,
        private PhoneVerificationStartStep $phoneVerificationStartStep,
        private PhoneVerificationFinishStep $phoneVerificationFinishStep,
        private BusinessTypeStep $businessTypeStep,
        private CompanyInfoStep $companyInfoStep,
        private TaxIdStep $taxIdOnboardingStep,
    ) {
    }

    public function get(OnboardingStepType $stepType): OnboardingStepInterface
    {
        return match ($stepType) {
            OnboardingStepType::VerifyEmail => $this->emailVerificationStep,
            OnboardingStepType::VerifyPhoneStart => $this->phoneVerificationStartStep,
            OnboardingStepType::VerifyPhoneFinish => $this->phoneVerificationFinishStep,
            OnboardingStepType::BusinessType => $this->businessTypeStep,
            OnboardingStepType::CompanyInfo => $this->companyInfoStep,
            OnboardingStepType::TaxId => $this->taxIdOnboardingStep,
        };
    }

    /**
     * @throws OnboardingException
     */
    public function buildState(Company $company, ?OnboardingStepType $currentStep): OnboardingState
    {
        $allSteps = [
            OnboardingStepType::VerifyEmail,
            OnboardingStepType::VerifyPhoneStart,
            OnboardingStepType::VerifyPhoneFinish,
            OnboardingStepType::BusinessType,
            OnboardingStepType::CompanyInfo,
            OnboardingStepType::TaxId,
        ];

        // Default to the first step the user can revisit
        if (!$currentStep) {
            foreach ($allSteps as $stepType) {
                $step = $this->get($stepType);
                if ($step->canRevisit($company)) {
                    $currentStep = $stepType;
                    break;
                }
            }

            if (!$currentStep) {
                throw new OnboardingException('Could not resolve current step');
            }
        }

        $currentStepId = (int) array_search($currentStep, $allSteps);

        // Validate that all previous steps from the current step
        // have been satisfied.
        $index = 0;
        while ($index < $currentStepId) {
            $stepType = $allSteps[$index];
            $step = $this->get($stepType);
            if ($step->mustPerform($company)) {
                throw new OnboardingException('An onboarding step has been skipped.');
            }
            ++$index;
        }

        // Traverse backwards through all steps from current step to find a supported step
        $previousStep = null;
        $index = $currentStepId - 1;
        while ($index > 0 && !$previousStep) {
            $stepType = $allSteps[$index];
            $step = $this->get($stepType);
            if ($step->canRevisit($company)) {
                $previousStep = $stepType;
            }
            --$index;
        }

        // Traverse forwards through all steps from current step to find a supported step
        $nextStep = null;
        $index = $currentStepId + 1;
        while ($index < count($allSteps) && !$nextStep) {
            $stepType = $allSteps[$index];
            $step = $this->get($stepType);
            if ($step->canRevisit($company)) {
                $nextStep = $stepType;
            }
            ++$index;
        }

        return new OnboardingState(
            allSteps: $allSteps,
            currentStep: $currentStep,
            previousStep: $previousStep,
            nextStep: $nextStep,
        );
    }
}
