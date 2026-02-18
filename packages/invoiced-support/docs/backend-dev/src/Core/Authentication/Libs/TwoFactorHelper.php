<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Interfaces\TwoFactorInterface;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\LoginDevice;
use App\Core\Authentication\Models\User;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class TwoFactorHelper implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private const COOKIE_NAME = 'LoginDeviceMfa';

    public function __construct(
        private TwoFactorInterface $twoFactor,
        private StorageInterface $storage,
        private LoginHelper $loginHelper,
        private RememberMeHelper $rememberMeHelper,
    ) {
    }

    /**
     * Checks if the user needs 2FA verification.
     */
    public static function needsVerification(User $user, Request $request): bool
    {
        if (!$user->authy_id > 0 || !$user->verified_2fa) {
            return false;
        }

        // check for a remembered device cookie
        if ($request->cookies->has(self::COOKIE_NAME)) {
            $loginDevice = LoginDevice::where('identifier', $request->cookies->get(self::COOKIE_NAME))
                ->where('user_id', $user)
                ->count();
            if ($loginDevice) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifies a user's 2FA token.
     *
     * @throws AuthException when the token cannot be verified
     */
    public function verify(Request $request, User $user, string $token, bool $remember): void
    {
        try {
            $this->twoFactor->verify($user, $token);
        } catch (AuthException $e) {
            $this->statsd->increment('security.failed_2fa_verification');

            throw $e;
        }

        // mark the user as 2FA verified, now and for the session
        $user->markTwoFactorVerified();
        $user->setIsFullySignedIn();
        $this->createAccountVerificationEvent($user, $request);

        if ($remember) {
            $this->rememberMeHelper->rememberUser($request, $user);
        }

        $this->storage->markTwoFactorVerified($request);

        // set a cookie to remember this device and not ask again for 90 days
        $cookieBag = $this->loginHelper->getCookieBag();
        $loginDeviceId = $request->cookies->get('LoginDevice');
        if ($cookieBag->has('LoginDevice')) {
            $loginDeviceId = $cookieBag->get('LoginDevice')->getValue();
        }

        if ($loginDeviceId) {
            $sessionCookie = session_get_cookie_params();
            $cookie = new Cookie(
                name: self::COOKIE_NAME,
                value: $loginDeviceId,
                expire: time() + 86400 * 90, // 90 days
                secure: $sessionCookie['secure'],
                sameSite: $sessionCookie['secure'] ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX
            );
            $cookieBag->set(self::COOKIE_NAME, $cookie);
        }

        $this->statsd->increment('security.2fa_verification');
    }

    private function createAccountVerificationEvent(User $user, Request $request): void
    {
        $accountEvent = new AccountSecurityEvent();
        $accountEvent->user_id = (int) $user->id();
        $accountEvent->type = AccountSecurityEvent::VERIFY_MFA;
        $accountEvent->ip = (string) $request->getClientIp();
        $accountEvent->user_agent = (string) $request->headers->get('User-Agent');
        $accountEvent->auth_strategy = '2fa';
        $accountEvent->save();
    }
}
