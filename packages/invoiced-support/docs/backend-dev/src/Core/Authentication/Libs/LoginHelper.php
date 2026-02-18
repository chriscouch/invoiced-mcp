<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Event\PostLoginEvent;
use App\Core\Authentication\Event\PostLogoutEvent;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Models\User;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class LoginHelper implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private ParameterBag $cookieBag;

    public function __construct(
        private Connection $database,
        private StorageInterface $storage,
        private UserContext $userContext,
        private EventDispatcherInterface $eventDispatcher,
        private RememberMeHelper $rememberMeHelper,
        private TwoFactorHelper $twoFactorHelper,
    ) {
        $this->cookieBag = new ParameterBag();
    }

    /**
     * Builds a signed in user object for a given user and saves
     * the user into the session storage. This method should be used
     * by authentication strategies to build a signed in session once
     * a user is authenticated.
     *
     * NOTE: If 2FA is enabled, and a user requires it, then the returned
     * user will not be marked as signed in. It's up to the middleware
     * layer to detect when a user needs 2FA and act accordingly.
     *
     * @throws AuthException
     */
    public function signInUser(Request $request, User $user, string $strategy, ?int $lifetime = null): User
    {
        // Apply a custom session lifetime and store in a cookie.
        // If no lifetime is provided then explicitly
        // clear the existing cookie.
        $sessionCookie = session_get_cookie_params();
        if ($lifetime > 0) {
            if (!headers_sent() && PHP_SESSION_ACTIVE !== session_status()) {
                ini_set('session.gc_maxlifetime', (string) $lifetime);
                session_set_cookie_params($lifetime);
            }

            $cookie = new Cookie(
                name: 'SessionLifetime',
                value: (string) $lifetime,
                expire: time() + $lifetime,
                secure: $sessionCookie['secure'],
                sameSite: $sessionCookie['secure'] ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX
            );
            $this->cookieBag->set($cookie->getName(), $cookie);
        } else {
            $cookie = new Cookie(
                name: 'SessionLifetime',
                value: null,
                expire: 1,
                secure: $sessionCookie['secure'],
                sameSite: $sessionCookie['secure'] ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX
            );
            $this->cookieBag->set($cookie->getName(), $cookie);
        }

        // sign in the user with the session storage
        $this->storage->signIn($user, $request);

        // if a user needs 2FA verification then they cannot
        // be completely signed in until they verify using 2FA
        if (!$user->isTwoFactorVerified() && $this->twoFactorHelper->needsVerification($user, $request)) {
            $user->setIsFullySignedIn(false);
        } else {
            // mark the user model as not needing second factor verification
            $user->setIsFullySignedIn();
        }

        // emit an event
        $event = new PostLoginEvent($user, $request, $strategy);
        $this->eventDispatcher->dispatch($event);

        $this->userContext->set($user);

        return $user;
    }

    /**
     * Gets the currently authenticated user.
     *
     * @throws AuthException
     */
    public function getAuthenticatedUser(Request $request): ?User
    {
        // Check for an authenticated user in the session
        $user = $this->storage->getAuthenticatedUser($request);

        // Check for an authenticated user in a remember me cookie
        if (!$user) {
            $user = $this->rememberMeHelper->getUserRememberMe($request);
        }

        // If no authenticated user is available then sign in a guest user
        if (!$user) {
            return null;
        }

        // check if the user needs 2FA verification
        if (!$user->isTwoFactorVerified() && $this->twoFactorHelper->needsVerification($user, $request)) {
            $user->setIsFullySignedIn(false);
        }

        return $user;
    }

    /**
     * Logs the authenticated user out.
     *
     * @throws AuthException when the user cannot be signed out
     */
    public function logout(Request $request): void
    {
        // Sign out from the session
        $this->storage->signOut($request);

        // Destroy any remember me cookie
        $this->rememberMeHelper->destroyRememberMeCookie($request);

        // Emit a logout event
        if ($this->userContext->has() && $user = $this->userContext->get()) {
            $event = new PostLogoutEvent($user, $request);
            $this->eventDispatcher->dispatch($event);
        }

        // Clear the current user context
        $this->userContext->clear();

        $this->statsd->increment('security.logout');
    }

    /**
     * Invalidates all sessions for a given user.
     */
    public function signOutAllSessions(User $user): void
    {
        // invalidate any active sessions
        $this->database->update('ActiveSessions', [
            'valid' => 0,
        ], [
            'user_id' => $user->id(),
        ]);

        // invalidate any remember me sessions
        $this->database->delete('PersistentSessions', [
            'user_id' => $user->id(),
        ]);
    }

    public function setStorage(StorageInterface $storage): void
    {
        $this->storage = $storage;
    }

    /**
     * Gets the cookie bag.
     */
    public function getCookieBag(): ParameterBag
    {
        return $this->cookieBag;
    }
}
