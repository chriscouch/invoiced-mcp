<?php

namespace App\EventSubscriber;

use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\Template;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TemplateSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function onBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        $template = $event->getEntityInstance();
        if (!$template instanceof Template) {
            return;
        }

        // look up the tenant and set it on the Template entity
        // or else it cannot be saved.
        $tenant = $this->managerRegistry->getRepository(Company::class)
            ->find($template->getTenantId());
        if (!$tenant instanceof Company) {
            throw new EntityNotFoundException(['entity_name' => 'Company', 'entity_id_name' => 'id', 'entity_id_value' => $template->getTenantId()]);
        }
        $template->setTenant($tenant);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforeEntityPersistedEvent',
        ];
    }
}
