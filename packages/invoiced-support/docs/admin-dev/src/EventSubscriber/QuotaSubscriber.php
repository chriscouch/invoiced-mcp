<?php

namespace App\EventSubscriber;

use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\Quota;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QuotaSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function onBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        $quota = $event->getEntityInstance();
        if (!$quota instanceof Quota) {
            return;
        }

        // look up the tenant and set it on the quota entity
        // or else it cannot be saved.
        $tenant = $this->managerRegistry->getRepository(Company::class)
            ->find($quota->getTenantId());
        if (!$tenant instanceof Company) {
            throw new EntityNotFoundException(['entity_name' => 'Company', 'entity_id_name' => 'id', 'entity_id_value' => $quota->getTenantId()]);
        }
        $quota->setTenant($tenant);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforeEntityPersistedEvent',
        ];
    }
}
