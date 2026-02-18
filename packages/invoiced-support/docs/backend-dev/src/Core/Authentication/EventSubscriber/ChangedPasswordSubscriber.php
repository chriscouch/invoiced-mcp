<?php

namespace App\Core\Authentication\EventSubscriber;

use App\Core\Authentication\Event\ChangedPasswordEvent;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Traits\ChangeAgentHelperTrait;
use App\Core\Mailer\Mailer;
use App\Core\Utils\IpLookup;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ChangedPasswordSubscriber implements EventSubscriberInterface
{
    use ChangeAgentHelperTrait;

    public function __construct(
        private LoginHelper $loginHelper,
        private Mailer $mailer,
        private IpLookup $ipLookup,
    ) {
    }

    public function handleChangedPassword(ChangedPasswordEvent $event): void
    {
        $this->createSecurityEvent($event);

        // changing the password signs the user out everywhere
        $this->loginHelper->signOutAllSessions($event->user);
        $this->loginHelper->logout($event->request);

        $this->sendEmail($event);
    }

    private function createSecurityEvent(ChangedPasswordEvent $event): void
    {
        $accountEvent = new AccountSecurityEvent();
        $accountEvent->user_id = (int) $event->user->id();
        $accountEvent->type = AccountSecurityEvent::CHANGE_PASSWORD;
        $accountEvent->ip = (string) $event->request->getClientIp();
        $accountEvent->user_agent = (string) $event->request->headers->get('User-Agent');
        $accountEvent->saveOrFail();
    }

    private function sendEmail(ChangedPasswordEvent $event): void
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
            'subject' => 'Your password was changed',
        ], 'password-changed', [
            'username' => $event->user->name(true),
            'ip' => $ip,
            'deviceType' => $deviceType,
            'location' => $ipinfo['location'],
            'timestamp' => $timestamp,
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ChangedPasswordEvent::class => 'handleChangedPassword',
        ];
    }
}
