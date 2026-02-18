<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Models\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class RememberMeHelper
{
    public function __construct(
        private Connection $database,
        private StorageInterface $storage,
        private LoginHelper $loginHelper,
    ) {
    }

    /**
     * Sets a remember me cookie for the user which will cause their
     * session to be remembered once it expires.
     */
    public function rememberUser(Request $request, User $user): void
    {
        $rememberMeCookie = new RememberMeCookie($user->email(), (string) $request->headers->get('User-Agent'));
        $this->sendRememberMeCookie($request, $user, $rememberMeCookie);
    }

    /**
     * Destroys the remember me cookie.
     */
    public function destroyRememberMeCookie(Request $request): void
    {
        $cookie = $this->getRememberMeCookie($request);
        if ($cookie) {
            $cookie->destroy();
        }

        $sessionCookie = session_get_cookie_params();
        $cookie = new Cookie(
            name: $this->rememberMeCookieName($request),
            value: '',
            expire: time() - 86400,
            domain: $sessionCookie['domain'],
            secure: $sessionCookie['secure'],
            sameSite: $sessionCookie['secure'] ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX
        );
        $this->loginHelper->getCookieBag()->set($cookie->getName(), $cookie);
    }

    /**
     * Tries to get an authenticated user via remember me.
     */
    public function getUserRememberMe(Request $request): ?User
    {
        // retrieve and verify the remember me cookie
        $cookie = $this->getRememberMeCookie($request);
        if (!$cookie) {
            return null;
        }

        $user = $cookie->verify($request, $this->database);
        if (!$user) {
            $this->destroyRememberMeCookie($request);

            return null;
        }

        $signedInUser = $this->loginHelper->signInUser($request, $user, 'remember_me');

        // generate a new remember me cookie for the next time,
        // using the same series
        $rememberMeCookie = new RememberMeCookie($user->email(), (string) $request->headers->get('User-Agent'), $cookie->getSeries());
        $this->sendRememberMeCookie($request, $user, $rememberMeCookie);

        return $signedInUser;
    }

    /**
     * Gets the decoded remember me cookie from the request.
     */
    private function getRememberMeCookie(Request $request): ?RememberMeCookie
    {
        $cookie = $request->cookies->get($this->rememberMeCookieName($request));
        if (!$cookie) {
            return null;
        }

        return RememberMeCookie::decode($cookie);
    }

    /**
     * Stores a remember me session cookie on the response.
     */
    private function sendRememberMeCookie(Request $request, User $user, RememberMeCookie $rememberMeCookie): void
    {
        // send the cookie with the same properties as the session cookie
        $sessionCookie = session_get_cookie_params();
        $cookie = new Cookie(
            name: $this->rememberMeCookieName($request),
            value: $rememberMeCookie->encode(),
            expire: $rememberMeCookie->getExpires(time()),
            domain: $sessionCookie['domain'],
            secure: $sessionCookie['secure'],
            sameSite: $sessionCookie['secure'] ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX
        );
        $this->loginHelper->getCookieBag()->set($cookie->getName(), $cookie);

        // save the cookie in the DB
        $rememberMeCookie->persist($user);

        // mark the session as remembered
        $this->storage->markRemembered($request);
    }

    public function getLoginHelper(): LoginHelper
    {
        return $this->loginHelper;
    }

    private function rememberMeCookieName(Request $request): string
    {
        if (!$request->hasSession()) {
            return 'remember';
        }

        return $request->getSession()->getName().'-remember';
    }
}
