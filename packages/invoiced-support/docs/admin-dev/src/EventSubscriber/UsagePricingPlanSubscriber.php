<?php

namespace App\EventSubscriber;

use App\Entity\Invoiced\BillingProfile;
use App\Entity\Invoiced\Company;
use App\Entity\Invoiced\UsagePricingPlan;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsagePricingPlanSubscriber implements EventSubscriberInterface
{
    private ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function onBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        $pricingPlan = $event->getEntityInstance();
        if (!$pricingPlan instanceof UsagePricingPlan) {
            return;
        }

        // look up the tenant and set it on the pricing plan entity
        // or else it cannot be saved.
        if ($tenantId = $pricingPlan->getTenantId()) {
            $tenant = $this->managerRegistry->getRepository(Company::class)
                ->find($tenantId);
            if (!$tenant instanceof Company) {
                throw new EntityNotFoundException(['entity_name' => 'Company', 'entity_id_name' => 'id', 'entity_id_value' => $tenantId]);
            }
            $pricingPlan->setTenant($tenant);
        }

        // look up the tenant and set it on the pricing plan entity
        // or else it cannot be saved.
        if ($billingProfileId = $pricingPlan->getBillingProfileId()) {
            $billingProfile = $this->managerRegistry->getRepository(BillingProfile::class)
                ->find($billingProfileId);
            if (!$billingProfile instanceof BillingProfile) {
                throw new EntityNotFoundException(['entity_name' => 'BillingProfile', 'entity_id_name' => 'id', 'entity_id_value' => $billingProfileId]);
            }
            $pricingPlan->setBillingProfile($billingProfile);
        }

        // Do not allow simultaneously setting tenant and billing profile
        if ($pricingPlan->getTenantId() && $pricingPlan->getBillingProfileId()) {
            throw new RuntimeException('You cannot set both the billing profile and tenant');
        }

        if ($pricingPlan->getBillingProfileId() && 5 != $pricingPlan->getUsageType()) {
            throw new RuntimeException('You cannot set this usage type on a billing profile');
        }

        if ($pricingPlan->getTenantId() && 5 == $pricingPlan->getUsageType()) {
            throw new RuntimeException('You cannot set this usage type on a tenant');
        }

        // let's check does this record exists in the database - by tenant_id
        $existing = $this->managerRegistry->getRepository(UsagePricingPlan::class)
            ->findOneBy([
                'usage_type' => $pricingPlan->getUsageType(),
                'tenant_id' => $pricingPlan->getTenantId(),
            ]);
        if ($existing instanceof UsagePricingPlan) {
            throw new RuntimeException('Record already exists in the database');
        }

        // let's check does this record exists in the database - by billing_profile_id
        $existing = $this->managerRegistry->getRepository(UsagePricingPlan::class)
            ->findOneBy([
                'usage_type' => $pricingPlan->getUsageType(),
                'billing_profile_id' => $pricingPlan->getBillingProfileId(),
            ]);
        if ($existing instanceof UsagePricingPlan) {
            throw new RuntimeException('Record already exists in the database');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforeEntityPersistedEvent',
        ];
    }
}
