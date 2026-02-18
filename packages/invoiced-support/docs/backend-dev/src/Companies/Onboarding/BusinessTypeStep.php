<?php

namespace App\Companies\Onboarding;

use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Core\Orm\Exception\ModelException;
use Symfony\Component\HttpFoundation\Request;

class BusinessTypeStep implements OnboardingStepInterface
{
    public function mustPerform(Company $company): bool
    {
        return !$company->type;
    }

    public function canRevisit(Company $company): bool
    {
        return true;
    }

    public function handleSubmit(Company $company, Request $request): void
    {
        try {
            $company->type = (string) $request->request->get('entity_type');
            if (!$company->type) {
                throw new OnboardingException('You must select an entity type.');
            }

            $company->saveOrFail();
        } catch (ModelException $e) {
            throw new OnboardingException($e->getMessage(), 'entity_type');
        }
    }
}
