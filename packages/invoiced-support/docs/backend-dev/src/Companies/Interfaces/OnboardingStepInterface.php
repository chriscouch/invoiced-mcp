<?php

namespace App\Companies\Interfaces;

use App\Companies\Exception\OnboardingException;
use App\Companies\Models\Company;
use Symfony\Component\HttpFoundation\Request;

interface OnboardingStepInterface
{
    /**
     * This indicates that a step is required to complete onboarding
     * for a company.
     */
    public function mustPerform(Company $company): bool;

    /**
     * This indicates that an already completed step can be revisited by a company
     * during onboarding.
     */
    public function canRevisit(Company $company): bool;

    /**
     * @throws OnboardingException
     */
    public function handleSubmit(Company $company, Request $request): void;
}
