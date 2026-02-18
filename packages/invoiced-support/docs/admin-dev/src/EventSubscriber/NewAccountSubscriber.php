<?php

namespace App\EventSubscriber;

use App\Entity\CustomerAdmin\AuditEntry;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use App\Event\CompanyCreatedEvent;
use Symfony\Component\Security\Core\Security;

class NewAccountSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $registry;
    private Security $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        $this->registry = $registry;
        $this->security = $security;
    }

    public function onCompanyCreatedEvent(CompanyCreatedEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        $csEntityManger = $this->registry->getManager('CustomerAdmin_ORM');
        $entry = new AuditEntry();
        $entry->setTimestamp(new \DateTime());
        $entry->setUser($user->getUsername());
        $entry->setAction('new_company');
        $entry->setContext((string) $event->getId());
        $csEntityManger->persist($entry);
        $csEntityManger->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanyCreatedEvent::class => 'onCompanyCreatedEvent',
        ];
    }
}
