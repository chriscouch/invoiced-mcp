<?php

namespace App\EventSubscriber;

use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\ProductPricingPlan;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPricingPlanSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function onBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        $pricingPlan = $event->getEntityInstance();
        if (!$pricingPlan instanceof ProductPricingPlan) {
            return;
        }

        // look up the tenant and set it on the pricing plan entity
        // or else it cannot be saved.
        $tenant = $this->managerRegistry->getRepository(Company::class)
            ->find($pricingPlan->getTenantId());
        if (!$tenant instanceof Company) {
            throw new EntityNotFoundException(['entity_name' => 'Company', 'entity_id_name' => 'id', 'entity_id_value' => $pricingPlan->getTenantId()]);
        }
        $pricingPlan->setTenant($tenant);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforeEntityPersistedEvent',
        ];
    }
}
