<?php

namespace App\EventSubscriber;

use App\Entity\CustomerAdmin\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Security;

class TimeZoneSubscriber implements EventSubscriberInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            // use the signed in user's time zone
            date_default_timezone_set($user->getTimeZone());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => 'onKernelRequest',
        ];
    }
}
