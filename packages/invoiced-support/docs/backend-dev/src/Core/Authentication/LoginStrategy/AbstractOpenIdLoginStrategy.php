<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Companies\FraudScore\IpAddressFraudScore;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\RememberMeHelper;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Traits\IpLoginCheckTrait;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\RandomString;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\Traits\OAuthIntegrationTrait;
use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use stdClass;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

abstract class AbstractOpenIdLoginStrategy extends AbstractLoginStrategy implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;
    use OAuthIntegrationTrait;
    use IpLoginCheckTrait;

    private CachedKeySet|Key $keySet;
    protected stdClass $lastToken;

    public function __construct(
        private IpAddressFraudScore $ipAddressFraudScore,
        LoginHelper $loginHelper,
        private RememberMeHelper $rememberMeHelper,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private CacheItemPoolInterface $cache,
        protected HttpClientInterface $httpClient,
        protected array $settings,
        private UrlGeneratorInterface $urlGenerator,
        private UserRegistration $userRegistration,
    ) {
        parent::__construct($loginHelper);
    }

    /**
     * Looks up an existing user based on the claimed ID and/or OpenID attributes.
     *
     * @throws AuthException
     */
    abstract protected function getUser(string $claimedId, array $attributes): ?User;

    /**
     * Generates the parameters for creating a new user given OpenID attributes.
     *
     * @throws AuthException
     */
    abstract protected function getNewUserParams(array $attributes): array;

    public function getId(): string
    {
        return $this->settings['strategyId'];
    }

    /**
     * Starts the OpenID Connect login process.
     */
    public function start(): RedirectResponse
    {
        // generate a CSRF token as a random state value
        $state = $this->csrfTokenManager->getToken('oauthState')->getValue();

        return new RedirectResponse($this->getAuthorizationUrl($state), 302, [
            // do not cache this page
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }

    public function getRedirectUrl(): string
    {
        $url = $this->urlGenerator->generate($this->settings['redirectPath'], [], UrlGeneratorInterface::ABSOLUTE_URL);

        // HTTPS is required of redirect URLs
        return str_replace('http://', 'https://', $url);
    }

    /**
     * Authenticates the user using OpenID Connect.
     *
     * @throws AuthException
     */
    public function authenticate(Request $request): User
    {
        // verify the state sent back matches our session
        $csrfToken = new CsrfToken('oauthState', (string) $request->query->get('state'));
        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new UnauthorizedHttpException('');
        }

        // If we have a code from the OAuth 2.0 flow then
        // we exchange that for an access token.
        $code = $request->query->get('code');
        if (!$code) {
            $errorMsg = $request->query->get('error_description');
            if (!$errorMsg) {
                $errorMsg = $request->query->get('error');
            }

            throw new AuthException('Connection failed. '.$errorMsg);
        }

        return $this->handleAuthorizationCode($code, $request);
    }

    /**
     * @throws AuthException
     */
    public function handleAuthorizationCode(string $code, Request $request): User
    {
        // Exchange the authorization code for an access token
        $this->lastToken = $this->exchangeAuthCodeForToken($code);

        // Now that we've made it this far we can verify the OpenID token.
        $tokenData = $this->decodeOpenIdToken($this->lastToken->id_token);

        // Token is valid, sign in the user.
        return $this->performSignIn($tokenData['sub'], $tokenData, $request);
    }

    /**
     * @throws AuthException
     */
    protected function exchangeAuthCodeForToken(string $code): stdClass
    {
        try {
            return $this->createToken('authorization_code', $code);
        } catch (OAuthException $e) {
            throw new AuthException($e->getMessage());
        }
    }

    /**
     * Only used for testing.
     */
    public function setKey(Key $key): void
    {
        $this->keySet = $key;
    }

    protected function getKeySet(): CachedKeySet|Key
    {
        if (!isset($this->keySet)) {
            $this->keySet = new CachedKeySet(
                $this->getJwksUrl(),
                new Psr18Client($this->httpClient),
                new HttpFactory(),
                $this->cache,
                null,
                true,
                $this->settings['defaultAlg'] ?? null,
            );
        }

        return $this->keySet;
    }

    public function getJwksUrl(): string
    {
        return $this->settings['jwksUrl'];
    }

    /**
     * Verifies an OpenID Connect JWT token. If verified returns the
     * payload of the JWT token.
     *
     * @throws AuthException
     */
    protected function decodeOpenIdToken(string $idToken): array
    {
        try {
            return (array) JWT::decode($idToken, $this->getKeySet());
        } catch (Throwable $e) {
            $this->logger->error('Unable to verify OpenID Connect token', ['exception' => $e]);

            throw new AuthException('Sorry, we were unable to verify your ID token. Please try again.');
        }
    }

    /**
     * Loads user info after obtaining an access token
     * from the configured userinfo endpoint.
     *
     * @throws AuthException
     */
    protected function getUserInfo(stdClass $token): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->settings['userinfoUrl'], [
                'auth_bearer' => $token->access_token,
            ]);

            return $response->toArray();
        } catch (HttpExceptionInterface $e) {
            $response = $e->getResponse();
            $body = $response->getContent(false);

            throw new AuthException('Could not load user info: '.$body, $response->getStatusCode(), $e);
        } catch (TransportExceptionInterface $e) {
            throw new AuthException('Could not load user info due to a connection error', $e->getCode(), $e);
        }
    }

    /**
     * Signs in an Invoiced user through OpenID Connect.
     *
     * @throws AuthException
     */
    protected function performSignIn(string $claimedId, array $tokenData, Request $request): User
    {
        // Check for an existing user
        $user = $this->getUser($claimedId, $tokenData);

        // When no user is found then we create a new user
        if (!$user instanceof User) {
            $userParams = $this->getNewUserParams($tokenData);
            $userParams['password'] = RandomString::generate(32, RandomString::CHAR_ALNUM).'aB1#';
            $userParams['ip'] = $request->getClientIp();

            $user = $this->userRegistration->registerUser($userParams, true, true);
        }

        $this->validateUser($user);

        // Check if the IP address is allowed to sign in
        if (!$user->disable_ip_check) {
            $this->checkIpAddress($request);
        }

        // sign in the user
        $this->loginHelper->signInUser($request, $user, $this->getId());
        $this->rememberMeHelper->rememberUser($request, $user);

        // mark the session for remember me
        // this is only needed when the user has 2fa verification
        $request->getSession()->set('remember_me', true);

        $this->statsd->increment('security.login', 1.0, ['strategy' => $this->getId()]);

        return $user;
    }
}
