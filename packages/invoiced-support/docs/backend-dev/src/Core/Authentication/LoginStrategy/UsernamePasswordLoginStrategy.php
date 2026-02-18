<?php

namespace App\Core\Authentication\LoginStrategy;

use App\Companies\FraudScore\IpAddressFraudScore;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\RememberMeHelper;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Traits\IpLoginCheckTrait;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class UsernamePasswordLoginStrategy extends AbstractLoginStrategy implements StatsdAwareInterface
{
    use StatsdAwareTrait;
    use IpLoginCheckTrait;

    private ?int $remainingAttempts = null;

    public function __construct(
        private RateLimiterFactory $usernameLoginLimiter,
        private RateLimiterFactory $ipLoginLimiter,
        private IpAddressFraudScore $ipAddressFraudScore,
        private RememberMeHelper $rememberMeHelper,
        LoginHelper $loginHelper,
    ) {
        parent::__construct($loginHelper);
    }

    public function getId(): string
    {
        return 'web';
    }

    /**
     * Handles a user authentication request.
     *
     * @throws AuthException when unable to authenticate the user
     */
    public function authenticate(Request $request): User
    {
        $username = (string) $request->request->get('username');
        $password = (string) $request->request->get('password');
        $remember = (bool) $request->request->get('remember');

        return $this->login($request, $username, $password, $remember);
    }

    /**
     * Performs a traditional username/password login and
     * creates a signed in user.
     *
     * @param bool $remember makes the session persistent
     *
     * @throws AuthException when the user cannot be signed in
     */
    public function login(Request $request, string $username, string $password, bool $remember = false): User
    {
        // Check for a rate limit violation for the IP address
        $ipLimiter = $this->ipLoginLimiter->create($request->getClientIp());
        if (!$ipLimiter->consume()->isAccepted()) {
            $this->statsd->increment('security.rate_limit_ip_login');
            $this->remainingAttempts = 0;

            throw new AuthException('Too many login attempts. Please try again later.');
        }

        // Check for a rate limit violation for the username
        $usernameLimiter = $this->usernameLoginLimiter->create($username);
        $limit = $usernameLimiter->consume();
        $window = '30 minutes';
        $this->remainingAttempts = $limit->getRemainingTokens();
        if (!$limit->isAccepted()) {
            $this->statsd->increment('security.rate_limit_username_login');

            throw new AuthException("This account has been locked due to too many failed sign in attempts. The lock is only temporary. Please try again after $window.");
        }

        try {
            $user = $this->getUserWithCredentials($username, $password);
        } catch (AuthException $e) {
            $this->statsd->increment('security.failed_login', 1.0, ['strategy' => $this->getId()]);

            throw $e;
        }

        // Check if the IP address is allowed to sign in
        if (!$user->disable_ip_check) {
            $this->checkIpAddress($request);
        }

        $this->loginHelper->signInUser($request, $user, $this->getId());

        if ($remember) {
            $this->rememberMeHelper->rememberUser($request, $user);
        }

        $this->statsd->increment('security.login', 1.0, ['strategy' => $this->getId()]);

        // Reset the username login counter after a successful login
        $usernameLimiter->reset();

        return $user;
    }

    /**
     * Fetches the user for a given username/password combination.
     *
     * @throws AuthException when a matching user cannot be found
     */
    public function getUserWithCredentials(string $username, string $password): User
    {
        if (empty($username)) {
            throw new AuthException('Please enter a valid username.');
        }

        if (empty($password)) {
            throw new AuthException('Please enter a valid password.');
        }

        // look the user up with the given username
        $usernameWhere = $this->buildUsernameWhere($username);
        $user = User::where($usernameWhere)->oneOrNull();

        if (!$user) {
            throw new AuthException('We could not find a match for that email address and password.');
        }

        if (!self::verifyPassword($user, $password)) {
            throw new AuthException('We could not find a match for that email address and password.');
        }

        $this->validateUser($user);

        // success!
        return $user;
    }

    /**
     * Checks if a given password matches the user's password.
     */
    public static function verifyPassword(User $user, string $password): bool
    {
        if (!$password) {
            return false;
        }

        $hashedPassword = $user->getHashedPassword();
        if (!$hashedPassword) {
            return false;
        }

        return password_verify($password, $hashedPassword);
    }

    public function getRemainingAttempts(): ?int
    {
        return $this->remainingAttempts;
    }

    /**
     * Builds a query string for matching the username.
     *
     * @param string $username username to match
     */
    private function buildUsernameWhere(string $username): string
    {
        $conditions = array_map(
            fn ($prop, $username) => $prop." = '".$username."'",
            User::$usernameProperties,
            array_fill(
                0,
                count(User::$usernameProperties),
                addslashes($username)
            )
        );

        return '('.implode(' OR ', $conditions).')';
    }
}
