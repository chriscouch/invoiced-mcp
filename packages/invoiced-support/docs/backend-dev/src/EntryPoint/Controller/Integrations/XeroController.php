<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Xero\Libs\XeroOAuth;
use App\Integrations\Xero\Models\XeroAccount;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class XeroController extends AbstractIntegrationsController
{
    #[Route(path: '/xero/select_org/{companyId}', name: 'xero_select_org', methods: ['GET', 'POST'])]
    public function xeroSelectOrg(Request $request, TenantContext $tenant, XeroOAuth $oauth, UserContext $userContext, string $dashboardUrl, string $companyId): Response
    {
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        /** @var XeroAccount $account */
        $account = $oauth->getAccount();
        $orgs = $oauth->getOrganizations($account);
        $companiesList = [];
        foreach ($orgs as $org) {
            $companiesList[$org['name']] = $org['id'];
        }

        $form = $this->createFormBuilder()
            ->add('company', ChoiceType::class, [
                'choices' => $companiesList,
                'label' => 'Organization',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Select',
                'attr' => [
                    'class' => 'btn-success',
                ],
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $id = $form->getData()['company'];
            $oauth->setOrg($id, $account);

            // redirect to dashboard
            return new RedirectResponse("$dashboardUrl/settings/apps/xero/accounting_sync", 302, [
                // do not cache this page
                'Cache-Control' => 'no-cache, no-store',
            ]);
        }

        return $this->render('integrations/xero/selectOrganization.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
