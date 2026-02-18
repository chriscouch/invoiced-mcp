<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Saml\SamlAuthFactory;
use App\Core\Authentication\Saml\SamlResponseSimplified;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

abstract class AbstractSamlLoginStrategy extends AbstractLoginStrategy implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public const AVAILABLE_RESPONSE_KEYS = [
        // onelogin
        'User.email',
        // auth0
        // MS AD
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        // okta
        'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
    ];

    public function __construct(
        protected SamlAuthFactory $authFactory,
        LoginHelper $loginHelper
    ) {
        parent::__construct($loginHelper);
    }

    abstract public function getId(): string;

    public function setFactory(SamlAuthFactory $authFactory): void
    {
        $this->authFactory = $authFactory;
    }

    /**
     * @throws AuthException
     */
    protected function parseResponse(CompanySamlSettings $samlSettings): Auth
    {
        $auth = $this->authFactory->get($samlSettings);

        // Parse the SAML response
        try {
            $auth->processResponse();
        } catch (Error|ValidationError $e) {
            $this->logger->error('Error processing SAML response', ['exception' => $e]);
            throw new AuthException($e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('Unexpected error parsing SAML response', ['exception' => $e]);

            throw new AuthException('The IdP request is malformed');
        }

        $errors = $auth->getErrors();
        if (!empty($errors)) {
            $this->logger->error('SAML authentication error', [
                'errors' => $errors,
                'error_reason' => $auth->getLastErrorReason(),
                'exception' => $auth->getLastErrorException(),
            ]);

            throw new AuthException(implode(', ', $errors));
        }
        if (!$auth->isAuthenticated()) {
            throw new AuthException('Not authenticated');
        }

        return $auth;
    }

    /**
     * @throws AuthException
     */
    public function doSignIn(Request $request, User $user, SamlResponseSimplified $response): void
    {
        $this->validateUser($user);

        // When SAML is used the authentication is deferred to the
        // Identity Provider. MFA is not necessary when a user signs
        // in from an IdP because the IdP should be responsible for it.
        $user->markTwoFactorVerified();

        // Set the session length from the SessionNotOnOrAfter value in the SAML response
        $sessionNotOnOrAfter = $response->getSessionNotOnOrAfter();
        $lifetime = null;
        if ($sessionNotOnOrAfter && $sessionNotOnOrAfter > time()) {
            $lifetime = $sessionNotOnOrAfter - time();
        }

        // Sign in the user
        $this->loginHelper->signInUser($request, $user, $this->getId(), $lifetime);
        $request->getSession()->set('company_restrictions', $this->getAvailableCompanies($user));

        $this->statsd->increment('security.login', 1.0, ['strategy' => $this->getId()]);
    }

    /**
     * @return ?int[]
     */
    protected function getAvailableCompanies(User $user): ?array
    {
        return null;
    }

    public function authenticate(Request $request): Response
    {
        return new Response();
    }
}
