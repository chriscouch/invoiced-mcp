<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

trait ReturnToCompanyTrait
{
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $submitButtonName = $context->getRequest()->request->all()['ea']['newForm']['btn'];

        if (Action::SAVE_AND_RETURN === $submitButtonName && $tenantId = $context->getEntity()->getInstance()->getTenantId()) {
            return $this->redirectToCompany($tenantId);
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    protected function redirectToCompany(int $tenantId): RedirectResponse
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->get(AdminUrlGenerator::class);

        return $this->redirect(
            $adminUrlGenerator
                ->setController(CompanyCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($tenantId)
                ->generateUrl()
        );
    }
}
