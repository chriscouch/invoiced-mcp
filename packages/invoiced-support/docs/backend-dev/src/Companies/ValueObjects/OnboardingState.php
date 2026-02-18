<?php

namespace App\Companies\ValueObjects;

use App\Companies\Enums\OnboardingStepType;

class OnboardingState
{
    /**
     * @param OnboardingStepType[] $allSteps
     */
    public function __construct(
        public readonly array $allSteps,
        public readonly OnboardingStepType $currentStep,
        public readonly ?OnboardingStepType $previousStep,
        public readonly ?OnboardingStepType $nextStep,
    ) {
    }

    public function getCurrentStepNumber(): int
    {
        // Add 1 to include sign up page
        // Add 1 to include zero index
        return ((int) array_search($this->currentStep, $this->allSteps)) + 2;
    }

    public function getTotalSteps(): int
    {
        // Add 1 to include sign up page
        return count($this->allSteps) + 1;
    }
}
