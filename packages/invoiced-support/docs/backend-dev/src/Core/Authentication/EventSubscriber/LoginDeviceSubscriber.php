<?php

namespace App\Core\Authentication\EventSubscriber;

use App\Core\Authentication\Event\PostLoginEvent;
use App\Core\Authentication\Event\PostLogoutEvent;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\LoginDevice;
use App\Core\Authentication\Traits\ChangeAgentHelperTrait;
use App\Core\Mailer\Mailer;
use App\Core\Utils\AppUrl;
use App\Core\Utils\IpLookup;
use App\Core\Utils\RandomString;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;

class LoginDeviceSubscriber implements EventSubscriberInterface
{
    use ChangeAgentHelperTrait;

    private const NOTIFY_STRATEGIES = ['web', 'google', '2fa'];

    public function __construct(
        private LoginHelper $loginHelper,
        private Mailer $mailer,
        private IpLookup $ipLookup,
    ) {
    }

    public function handleLogin(PostLoginEvent $event): void
    {
        $this->createAccountLoginEvent($event);
        $this->checkLoginDevice($event);
    }

    public function handleLogout(PostLogoutEvent $event): void
    {
        $this->createAccountLogoutEvent($event);
    }

    private function createAccountLoginEvent(PostLoginEvent $event): void
    {
        $accountEvent = new AccountSecurityEvent();
        $accountEvent->user_id = (int) $event->user->id();
        $accountEvent->type = AccountSecurityEvent::LOGIN;
        $accountEvent->ip = (string) $event->request->getClientIp();
        $accountEvent->user_agent = (string) $event->request->headers->get('User-Agent');
        $accountEvent->auth_strategy = $event->strategy;
        $accountEvent->save();
    }

    private function createAccountLogoutEvent(PostLogoutEvent $event): void
    {
        $accountEvent = new AccountSecurityEvent();
        $accountEvent->user_id = (int) $event->user->id();
        $accountEvent->type = AccountSecurityEvent::LOGOUT;
        $accountEvent->ip = (string) $event->request->getClientIp();
        $accountEvent->user_agent = (string) $event->request->headers->get('User-Agent');
        $accountEvent->auth_strategy = 'web';
        $accountEvent->save();
    }

    private function checkLoginDevice(PostLoginEvent $event): void
    {
        if (!in_array($event->strategy, self::NOTIFY_STRATEGIES)) {
            return;
        }

        // Do not notify if user created in past day
        if ($event->user->created_at >= strtotime('-1 day')) {
            return;
        }

        // Check if we've seen this device sign in for this user before
        $loginDevice = null;
        if ($event->request->cookies->has('LoginDevice')) {
            $loginDevice = LoginDevice::where('identifier', $event->request->cookies->get('LoginDevice'))
                ->where('user_id', $event->user)
                ->oneOrNull();
        }

        if (!$loginDevice) {
            // Create a new login device model
            $loginDevice = new LoginDevice();
            $loginDevice->user = $event->user;
            $loginDevice->identifier = RandomString::generate(32, RandomString::CHAR_ALNUM);
            $loginDevice->saveOrFail();

            // Store the identifier in a cookie
            $sessionCookie = session_get_cookie_params();
            $cookie = new Cookie(
                name: 'LoginDevice',
                value: $loginDevice->identifier,
                expire: time() + 86400 * 1460,
                secure: $sessionCookie['secure'],
                sameSite: $sessionCookie['secure'] ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX
            );
            $this->loginHelper->getCookieBag()->set($cookie->getName(), $cookie);

            // Send an email
            $this->sendEmail($event);
        }
    }

    private function sendEmail(PostLoginEvent $event): void
    {
        // Get an approximate location of the sign in
        $ip = (string) $event->request->getClientIp();
        $ipinfo = $this->getIpInfo($ip);

        // Generate the name of the device type that was used to sign in
        $deviceType = $this->getUserAgentDescription((string) $event->request->headers->get('User-Agent'));

        // Generate the timestamp of the sign in
        $timestamp = $this->getTimestamp($ipinfo['timezone']);

        // Send the email to the user
        $this->mailer->sendToUser($event->user, [
            'subject' => 'New Device Login',
        ], 'new-device-login', [
            'username' => $event->user->name(true),
            'ip' => $ip,
            'deviceType' => $deviceType,
            'location' => $ipinfo['location'],
            'timestamp' => $timestamp,
            'resetPasswordUrl' => AppUrl::get()->build().'/forgot',
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostLoginEvent::class => 'handleLogin',
            PostLogoutEvent::class => 'handleLogout',
        ];
    }
}
