<?php

namespace App\EventSubscriber;

use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\Feature;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FeatureSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function onBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        $feature = $event->getEntityInstance();
        if (!$feature instanceof Feature) {
            return;
        }

        // look up the tenant and set it on the feature entity
        // or else it cannot be saved.
        $tenant = $this->managerRegistry->getRepository(Company::class)
            ->find($feature->getTenantId());
        if (!$tenant instanceof Company) {
            throw new EntityNotFoundException(['entity_name' => 'Company', 'entity_id_name' => 'id', 'entity_id_value' => $feature->getTenantId()]);
        }
        $feature->setTenant($tenant);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforeEntityPersistedEvent',
        ];
    }
}
