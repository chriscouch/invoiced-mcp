<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\Countries;
use App\Core\Multitenant\TenantContext;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Command\SignUpFormProcessor;
use App\CustomerPortal\Exceptions\SignUpFormException;
use App\CustomerPortal\Libs\SignUpForm;
use App\CustomerPortal\Models\SignUpPage;
use App\CustomerPortal\ViewVariables\SignUpPageViewVariables;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Libs\SubscriptionPreview;
use App\SubscriptionBilling\Models\Subscription;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class SignUpPageCustomerPortalController extends AbstractCustomerPortalController
{
    private const SESSION_KEY_DISABLED_PAYMENTS_TEMPLATE = "sign_up_disabled_methods_%d_%d";

    #[Route(path: '/sign_up/{id}', name: 'customer_sign_up_page', methods: ['GET'])]
    public function customerSignUpPage(Request $request, string $id): Response
    {
        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        $signUpPage = $customer->signUpPage();
        if (!$signUpPage) {
            throw new NotFoundHttpException();
        }

        $url = $signUpPage->customerUrl($customer);

        $query = $request->query->all();
        if ($queryStr = http_build_query($query)) {
            $url .= '?'.$queryStr;
        }

        return new RedirectResponse($url);
    }

    #[Route(path: '/api/pages/{id}/{clientId}', name: 'submit_sign_up_page_api', requirements: ['id' => '[0-9a-zA-Z]+', 'clientId' => '[0-9a-zA-Z]+'], defaults: ['clientId' => null, 'no_database_transaction' => true], methods: ['POST'])]
    public function signUpPageSubmitApi(Request $request, SignInCustomer $signIn, SignUpFormProcessor $processor, SessionInterface $session, string $id, ?string $clientId): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_sign_up_page', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        // look up page
        $signUpPage = SignUpPage::findClientId($id);
        if (!$signUpPage) {
            throw new NotFoundHttpException('Sign up page not found');
        }

        $form = new SignUpForm($signUpPage, $portal->company());

        // look up customer
        if ($clientId) {
            $customer = Customer::findClientId($clientId);
            if (!$customer) {
                throw new NotFoundHttpException('Customer not found');
            }
            $form->setCustomer($customer);
        }

        try {
            $parameters = $request->request->all();
            $sessionKey = sprintf(
                self::SESSION_KEY_DISABLED_PAYMENTS_TEMPLATE,
                $signUpPage->id,
                $signUpPage->client_id
            );

            if ($session->has($sessionKey)) {
                $parameters['disabled_methods'] = $session->get($sessionKey);
            }
            $session->remove($sessionKey);
            [$customer, $subscription] = $processor->handleSubmit($form, $parameters, (string) $request->getClientIp(), (string) $request->headers->get('User-Agent'));
        } catch (SignUpFormException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        }

        // sign the newly created customer into the customer portal
        $response = new JsonResponse([
            'url' => $form->getThanksUrl($customer, $subscription),
        ]);

        // return thanks url
        return $signIn->signIn($customer, $response);
    }

    #[Route(path: '/pages/{id}/thanks', name: 'sign_up_page_thanks', requirements: ['id' => '[0-9a-zA-Z]+'], methods: ['GET'])]
    public function signUpPageThanks(string $id): Response
    {
        // look up page
        $signUpPage = SignUpPage::findClientId($id);

        if (!$signUpPage) {
            throw new NotFoundHttpException();
        }

        $plan = null;
        $portal = $this->customerPortalContext->getOrFail();
        if ($customer = $portal->getSignedInCustomer()) {
            $subscription = Subscription::where('customer', $customer->id())
                ->oneOrNull();
            if ($subscription) {
                $plan = $subscription->plan();
            }
        }

        return $this->render('customerPortal/signUpPages/signUpPageThanks.twig', [
            'plan' => $plan ? $plan->getCustomerFacingName() : null,
            'googleAnalyticsEvents' => [['category' => 'Customer Portal', 'action' => 'Signed Up', 'label' => $id]],
        ]);
    }

    #[Route(path: '/pages/{id<[0-9a-zA-Z]+>}/_bootstrap', name: 'sign_up_page_js_bootstrap', methods: ['GET'])]
    public function signUpPageJsBootstrap(SignUpPageViewVariables $viewVariables, string $id): Response
    {
        // look up page
        $signUpPage = SignUpPage::findClientId($id);
        if (!$signUpPage) {
            throw new NotFoundHttpException('Sign up page not found');
        }

        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();
        [$plans] = $viewVariables->getPlans($signUpPage, $company);
        $taxes = $viewVariables->getTaxes($company, $signUpPage->taxes());

        $config = [
            'plans' => $plans,
            'taxes' => $taxes,
        ];

        return new JsonResponse($config);
    }

    #[Route(path: '/pages/{id<[0-9a-zA-Z]+>}/_lookupCoupon', name: 'sign_up_page_lookup_coupon', methods: ['GET'])]
    public function signUpPageLookupCoupon(Request $request, SignUpPageViewVariables $viewVariables, string $id): Response
    {
        // look up page
        $signUpPage = SignUpPage::findClientId($id);
        if (!$signUpPage) {
            throw new NotFoundHttpException('Sign up page not found');
        }

        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();
        $form = new SignUpForm($signUpPage, $company);

        $couponId = (string) $request->query->get('id');
        $coupon = $form->lookupCoupon($couponId);
        if (!$coupon) {
            throw new NotFoundHttpException('Coupon not found');
        }

        return new JsonResponse($viewVariables->getCoupon($company, $coupon));
    }

    #[Route(path: '/api/subscriptions/preview', name: 'preview_subscription', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function previewSubscription(Request $request, TranslatorInterface $translator, TenantContext $tenant, SignUpPageViewVariables $viewVariables): Response
    {
        $taxAddress = $request->request->all('shipping');
        $company = $tenant->get();
        $customer = new Customer($taxAddress);

        $plan = (string) $request->request->get('plan');
        $quantity = (float) $request->request->get('quantity');
        $preview = new SubscriptionPreview($company);
        $preview->setPlan($plan)
            ->setDiscounts($request->request->all('discounts'))
            ->setAddons($request->request->all('addons'))
            ->setQuantity($quantity);

        try {
            // generate invoice
            $invoice = $preview->generate($customer)->getFirstInvoice();
        } catch (OperationException) {
            return new JsonResponse([], 400);
        }

        $countries = new Countries();
        $country = $countries->get($customer->country ?? $company->country ?? 'US');

        $taxIdLabel = $translator->trans('labels.tax_id', [], 'customer_portal');
        if (isset($country['tax_id'])) {
            $taxIdLabel = $country['tax_id']['company'];
        }

        return new JsonResponse([
            'total' => $invoice->total,
            'taxes' => $viewVariables->getTaxAmount($invoice),
            'has_tax_id' => $country ? array_value($country, 'buyer_has_tax_id') : null,
            'tax_id_label' => $taxIdLabel,
        ]);
    }

    /**
     * These routes must go last because they are more generic.
     */
    #[Route(path: '/pages/{id}/{clientId}', name: 'sign_up_page', requirements: ['id' => '[0-9a-zA-Z]+', 'clientId' => '[0-9a-zA-Z]+'], defaults: ['clientId' => null], methods: ['GET'])]
    public function signUpPage(Request $request, SignInCustomer $signIn, PaymentMethodViewFactory $paymentMethodViewFactory, SignUpPageViewVariables $viewVariables, SessionInterface $session, string $id, ?string $clientId): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        // look up page
        $signUpPage = SignUpPage::findClientId($id);
        if (!$signUpPage) {
            throw new NotFoundHttpException();
        }

        $form = new SignUpForm($signUpPage, $portal->company());
        if (!$form->signUpsAllowed()) {
            return $this->render('customerPortal/signUpPages/signUpsNotAllowed.twig');
        }

        // look up customer
        $customer = null;
        if ($clientId) {
            $customer = Customer::findClientId($clientId);
            if (!$customer) {
                throw new NotFoundHttpException();
            }
            $form->setCustomer($customer);

            // Check if customer is allowed to purchase. If not, then
            // they are already subscribed and we should send them to
            // their my account page.
            if (!$form->signUpsAllowedForCustomer($customer)) {
                if (!$portal->enabled()) {
                    throw new NotFoundHttpException();
                }

                // sign the customer into the customer portal before redirecting
                $response = new RedirectResponse(
                    $this->generatePortalUrl($portal, 'customer_portal_account')
                );
                $response = $signIn->signIn($customer, $response);

                return $response;
            }
        }

        $pageParams = $viewVariables->build($portal, $form, $request, $paymentMethodViewFactory, $customer);

        $disabledMethods = $pageParams['disabledMethods'];
        /** @var Customer $customer */
        $sessionKey = sprintf(
            self::SESSION_KEY_DISABLED_PAYMENTS_TEMPLATE,
            $signUpPage->id,
            $signUpPage->client_id
        );
        if ($disabledMethods) {
            $session->set($sessionKey, $disabledMethods);

            return $this->render('customerPortal/signUpPages/signUpPage.twig', $pageParams);
        }
        $session->remove($sessionKey);

        return $this->render('customerPortal/signUpPages/signUpPage.twig', $pageParams);
    }
}
