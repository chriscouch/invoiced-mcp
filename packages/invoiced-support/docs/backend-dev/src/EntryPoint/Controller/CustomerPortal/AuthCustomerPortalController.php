<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\Customer;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\Models\User;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalRedirect;
use App\CustomerPortal\Libs\CustomerPortalSecurityChecker;
use App\CustomerPortal\Libs\LoginSchemes\EmailLoginScheme;
use App\CustomerPortal\Models\CustomerPortalSession;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
class AuthCustomerPortalController extends AbstractCustomerPortalController
{
    private string $loginError = '';

    #[Route(path: '/login', name: 'login_form', methods: ['GET'])]
    public function loginForm(Request $request): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        // Redirect to a custom authentication URL
        if ($authUrl = $portal->getCustomAuthUrl()) {
            return new RedirectResponse($authUrl);
        }

        return $this->render('customerPortal/login.twig', [
            'email' => $request->request->get('email'),
            'error' => $this->loginError,
        ]);
    }

    #[Route(path: '/login', name: 'request_email_login', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function login(Request $request, EmailLoginScheme $scheme): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_login', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $email = $request->request->getString('email');
        $ip = (string) $request->getClientIp();
        $redirectTo = $request->query->get('redirect_to');
        $scheme->requestLogin($portal, $email, $ip, $redirectTo);

        return $this->render('customerPortal/loginLinkSent.twig', [
            'email' => $request->request->get('email'),
            'googleAnalyticsEvents' => [['category' => 'Customer Portal', 'action' => 'Requested Login', 'label' => $request->request->get('email')]],
        ]);
    }

    #[Route(path: '/customers', name: 'select_customer', methods: ['GET'])]
    public function selectCustomer(Request $request, CustomerPortalSecurityChecker $checker, CustomerHierarchy $hierarchy): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        $customers = $checker->getCustomersForEmail($portal, $hierarchy);
        if (!$customers) {
            // TODO localize this string
            $this->loginError = 'You are not in the customer list of any existing company. Please, contact your vendor.';

            return $this->loginForm($request);
        }

        $redirectTo = $request->query->get('redirect_to');
        if (1 === count($customers)) {
            return $this->redirect(
                $this->makeCustomerLoginUrl($portal, $customers[0], $redirectTo)
            );
        }

        $showAccountNumber = $portal->company()->defaultTheme()->show_customer_no;

        return $this->render('customerPortal/selectCustomer.twig', [
            'customers' => array_map(fn (Customer $customer) => [
                'name' => $customer->name,
                'address' => $customer->address(false),
                'accountNumber' => $showAccountNumber ? $customer->number : null,
                'url' => $this->makeCustomerLoginUrl($portal, $customer, $redirectTo),
            ], $customers),
        ]);
    }

    private function makeCustomerLoginUrl(CustomerPortal $portal, Customer $customer, ?string $redirectTo): string
    {
        $params = [
            'token' => $portal->generateLoginToken($customer, SignInCustomer::TEMPORARY_SIGNED_IN_TTL),
        ];
        if ($redirectTo) {
            $params['redirect_to'] = $redirectTo;
        }

        return $this->generatePortalUrl($portal, 'customer_portal_single_sign_on', $params);
    }

    #[Route(path: '/login/{token}', name: 'single_sign_on', methods: ['GET'])]
    public function loginWithToken(Request $request, SignInCustomer $signIn, string $token): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        // Intentionally not checking if the portal is enabled. If
        // an account uses the network to send invoices then it might
        // not have the customer portal feature flag.

        // verify the login token
        $customer = $portal->getCustomerFromToken($token);

        if (!$customer) {
            // TODO localize this string
            $this->loginError = 'This sign in link is invalid or has expired. Please enter your email address to request a new one.';

            return $this->loginForm($request);
        }

        // sign the customer into the customer portal
        $response = new RedirectResponse('/');

        // check for redirect in cookie or URL string
        // the query parameter has precedence
        $redirectTo = (string) $request->query->get('redirect_to');
        if ($redirect = CustomerPortalRedirect::get($redirectTo)) {
            $params = [];

            if ($redirect['requires_client_id'] ?? false) {
                $params['id'] = $customer->client_id;
            }

            if ($query = $redirect['query'] ?? null) {
                $params = array_merge($params, $query);
            }

            $response = new RedirectResponse($this->generatePortalUrl($portal, $redirect['route'], $params));
        } elseif ($request->cookies->has('redirect_after_login')) {
            $response = new RedirectResponse($request->cookies->get('redirect_after_login'));
            $response->headers->setCookie($this->clearCookie('redirect_after_login'));
        }

        return $signIn->signIn($customer, $response);
    }

    #[Route(path: '/start_session/{id}', name: 'start_session', methods: ['GET'])]
    public function startSession(Request $request, string $id): RedirectResponse
    {
        // Redirect to the intended page
        $url = base64_decode($request->query->getString('r'));
        $response = new RedirectResponse($url);

        // Store the session identifier in a cookie
        $session = CustomerPortalSession::getForIdentifier($id);
        if ($session) {
            $cookie = $this->makeCookie(CustomerPortalSession::COOKIE_NAME, $id, $session->expires->getTimestamp());
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    #[Route(path: '/logout', name: 'logout', methods: ['GET'])]
    public function logout(SignInCustomer $signIn): Response
    {
        return $signIn->signOut(new RedirectResponse('/'));
    }

    #[Route(path: '/signup', name: 'signup', methods: ['GET', 'POST'])]
    public function signup(Request $request, UserRegistration $userRegistration, TranslatorInterface $translator): Response
    {
        $form = $this->createFormBuilder(
            null,
            [
                'translation_domain' => 'customer_portal',
                'data_class' => User::class,
            ])
            ->add('first_name', TextType::class, [
                'label' => 'labels.first_name',
            ])
            ->add('last_name', TextType::class, [
                'label' => 'labels.last_name',
            ])
            ->add('email', EmailType::class, [
                'label' => 'labels.email_address',
            ])
            ->add('password', PasswordType::class, [
                'label' => 'labels.password',
            ])
            ->add('confirm_password', PasswordType::class, [
                'label' => 'labels.confirm_password',
                'mapped' => false,
            ])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            if ($user->password !== $form->get('confirm_password')->getData()) {
                $form->addError(new FormError($translator->trans('labels.password_mismatch', [], 'customer_portal')));
            } else {
                $params = [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'ip' => $request->getClientIp(),
                ];

                try {
                    $userRegistration->registerUser($params, false, false);

                    return $this->render('customerPortal/signupSuccess.twig');
                } catch (AuthException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }
        }

        return $this->render('customerPortal/signup.twig', [
            'form' => $form->createView(),
        ]);
    }
}
