<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractIntegrationsController extends AbstractController
{
    protected function checkEditPermission(Company $company, UserContext $userContext): void
    {
        $user = $userContext->get();
        if (!$user) {
            throw new NotFoundHttpException();
        }

        $member = Member::getForUser($user);
        if (!$member || !$company->memberCanEdit($member)) {
            throw new NotFoundHttpException();
        }
    }
}
