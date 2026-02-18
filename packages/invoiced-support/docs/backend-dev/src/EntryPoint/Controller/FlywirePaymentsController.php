<?php

namespace App\EntryPoint\Controller;

use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Exception\FlywireOnboardingException;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;
use Carbon\CarbonImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(host: '%app.domain%', schemes: '%app.protocol%')]
class FlywirePaymentsController extends AbstractController
{
    public function __construct(
        private readonly FlywirePaymentsOnboarding $onboarding,
        private readonly TenantContext $tenant,
        private readonly UserContext $userContext,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[Route(path: '/flywire/onboarding/{companyId}/start', name: 'flywire_onboarding_start', methods: ['GET', 'POST'])]
    public function flywireOnboardingStart(Request $request, string $dashboardUrl, string $companyId): Response
    {
        $company = $this->initiateOnboarding($companyId);

        $adyenAccount = AdyenAccount::queryWithTenant($company)->oneOrNull() ?? new AdyenAccount();
        // Check if the start page is needed
        if (!$this->onboarding->needsStartPage($adyenAccount)) {
            return $this->redirect(
                $this->onboarding->getOnboardingAdyenRedirectUrl($company)
            );
        }

        if (!trim($company->address1 ?? '') ||
            !trim($company->city ?? '') ||
            !trim($company->postal_code ?? '') ||
            !trim($company->country ?? '')
        ) {
            return $this->render('integrations/flywirePayments/error.twig', [
                'error' => 'You need to set up your company address before you can enable Flywire Payments. Please go to your company settings and fill in your address.',
                'url' => $dashboardUrl.'/settings/business?account='.$company->id,
            ]);
        }

        $form = $this->onboarding->makeForm($adyenAccount);
        $form->handleRequest($request);

        // Handle form submission
        if ($form->isSubmitted() && $form->isValid()) {
            $adyenAccount->industry_code = $form->get('industryCode')->getData();
            $adyenAccount->terms_of_service_acceptance_date = CarbonImmutable::now();
            $adyenAccount->terms_of_service_acceptance_user = $this->userContext->get();
            $adyenAccount->terms_of_service_acceptance_ip = $request->getClientIp();
            $adyenAccount->terms_of_service_acceptance_version = '2025-01-29';
            $adyenAccount->saveOrFail();

            if ($form->has('phone')) {
                $company->phone = $form->get('phone')->getData();
                $company->saveOrFail();
            }

            $company->features->enable('flywire_invoiced_payments');

            return $this->redirect(
                $this->onboarding->getOnboardingAdyenRedirectUrl($company)
            );
        }

        return $this->render('integrations/flywirePayments/start.twig', [
            'form' => $form->createView(),
            'pricing' => AdyenConfiguration::getPricingForAccount(true, $adyenAccount), // live mode setting does not matter
            '_defaultCurrency' => $company->currency,
            '_moneyOptions' => $company->moneyFormat(),
            'dashboardUrl' => $dashboardUrl,
        ]);
    }

    #[Route(path: '/flywire/onboarding/{companyId}/redirect', name: 'flywire_onboarding_redirect', methods: ['GET'])]
    public function flywireOnboardingRedirect(AdyenClient $adyen, string $adyenThemeId, string $companyId): Response
    {
        $company = $this->initiateOnboarding($companyId);
        $adyenAccount = AdyenAccount::queryWithTenant($company)->oneOrNull() ?? new AdyenAccount();

        if ($this->onboarding->needsStartPage($adyenAccount)) {
            return $this->redirectToRoute('flywire_onboarding_start', ['companyId' => $company->identifier]);
        }

        try {
            $this->onboarding->setupAccountForOnboarding($adyenAccount);
        } catch (FlywireOnboardingException $e) {
            return $this->render('integrations/flywirePayments/error.twig', [
                'error' => $e->getMessage(),
            ]);
        }

        // Create hosted onboarding link
        $onboardingLink = $adyen->createOnboardingLink((string) $adyenAccount->legal_entity_id, [
            'redirectUrl' => $this->generateUrl('flywire_onboarding_finish', ['companyId' => $company->identifier], UrlGeneratorInterface::ABSOLUTE_URL),
            'themeId' => $adyenThemeId,
            'settings' => [
                'requirePciSignEcommerce' => false,
                'requirePciSignEcomMoto' => false,
                'requirePciSignPos' => false,
                'requirePciSignPosMoto' => false,
            ],
        ]);

        return $this->redirect($onboardingLink['url']);
    }

    #[Route(path: '/flywire/onboarding/{companyId}/finish', name: 'flywire_onboarding_finish', methods: ['GET'])]
    public function flywireOnboardingFinish(string $dashboardUrl, string $companyId): Response
    {
        $company = $this->initiateOnboarding($companyId);

        return $this->redirect($dashboardUrl.'/settings/payments/account?account='.$company->id);
    }

    private function initiateOnboarding(string $companyId): Company
    {
        /** @var ?Company $company */
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        if (!$this->onboarding->canOnboard($company)) {
            throw new NotFoundHttpException();
        }

        $this->translator->setlocale('en_'.$company->country); /* @phpstan-ignore-line */

        return $company;
    }
}
