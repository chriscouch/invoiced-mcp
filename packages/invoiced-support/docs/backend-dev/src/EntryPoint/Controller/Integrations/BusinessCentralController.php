<?php

namespace App\EntryPoint\Controller\Integrations;

use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Integrations\BusinessCentral\BusinessCentralApi;
use App\Integrations\BusinessCentral\BusinessCentralOAuth;
use App\Integrations\OAuth\Models\OAuthAccount;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.domain%')]
class BusinessCentralController extends AbstractIntegrationsController
{
    #[Route(path: '/business_central/select_company/{companyId}', name: 'business_central_select_company', methods: ['GET', 'POST'])]
    public function businessCentralSelectCompany(Request $request, TenantContext $tenant, BusinessCentralOAuth $oauth, BusinessCentralApi $api, UserContext $userContext, string $dashboardUrl, string $companyId): Response
    {
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        /** @var OAuthAccount $account */
        $account = $oauth->getAccount();
        $companiesList = [];
        $environments = $api->getEnvironments($account);
        foreach ($environments as $environment) {
            $companies = $api->getCompanies($account, $environment->name);
            foreach ($companies as $company) {
                $name = $environment->name.' / '.$company->name;
                $id = $environment->name.'/'.$company->id;
                $companiesList[$name] = $id;
            }
        }

        $form = $this->createFormBuilder()
            ->add('company', ChoiceType::class, [
                'choices' => $companiesList,
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
            [$environmentId, $companyId] = explode('/', $id);
            $account->addMetadata('environment', $environmentId);
            $account->addMetadata('company', $companyId);
            $account->name = (string) array_search($id, $companiesList);
            $account->saveOrFail();

            // redirect to dashboard
            return new RedirectResponse("$dashboardUrl/settings/apps/business_central/accounting_sync", 302, [
                // do not cache this page
                'Cache-Control' => 'no-cache, no-store',
            ]);
        }

        return $this->render('integrations/businessCentral/selectCompany.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
