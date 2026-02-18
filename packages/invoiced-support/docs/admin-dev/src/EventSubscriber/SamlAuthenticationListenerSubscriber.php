<?php

namespace App\EventSubscriber;

use App\Entity\CustomerAdmin\User;
use Hslavich\OneloginSamlBundle\Security\Http\Authenticator\Passport\Badge\SamlAttributesBadge;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SamlAuthenticationListenerSubscriber implements EventSubscriberInterface
{
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        if (!$event->getPassport()->hasBadge(SamlAttributesBadge::class)) {
            return;
        }

        // set the user on the session so that the 2fa can be skipped
        /** @var User $user */
        $user = $event->getUser();
        $session = $event->getRequest()->getSession();
        $session->set('saml_user_id', $user->getId());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccessEvent',
        ];
    }
}
