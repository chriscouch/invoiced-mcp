<?php

namespace App\EventSubscriber;

use App\Entity\Invoiced\PurchasePageContext;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurchasePageContextSubscriber implements EventSubscriberInterface
{
    public function onBeforeEntityEditedEvent(BeforeEntityUpdatedEvent $event): void
    {
        $purchasePage = $event->getEntityInstance();
        if (!$purchasePage instanceof PurchasePageContext) {
            return;
        }

        if ($purchasePage->getCompletedAt()) {
            throw new RuntimeException('You cannot edit a completed page.');
        }
    }

    public function onBeforeEntityDeletedEvent(BeforeEntityDeletedEvent $event): void
    {
        $purchasePage = $event->getEntityInstance();
        if (!$purchasePage instanceof PurchasePageContext) {
            return;
        }

        if ($purchasePage->getCompletedAt()) {
            throw new RuntimeException('You cannot delete a completed page.');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityUpdatedEvent::class => 'onBeforeEntityEditedEvent',
            BeforeEntityDeletedEvent::class => 'onBeforeEntityDeletedEvent',
        ];
    }
}
