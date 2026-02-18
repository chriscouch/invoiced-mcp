<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'SubscriptionBillingSettings')]
class SubscriptionBillingSettings
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\Company')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Column(type: 'string')]
    private string $after_subscription_nonpayment;
    #[ORM\Column(type: 'boolean')]
    private bool $subscription_draft_invoices;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;

    public function __toString(): string
    {
        return $this->tenant->getName().' Settings';
    }

    public function gettenant_id(): int
    {
        return $this->tenant_id;
    }

    public function getTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setTenantId(int $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getAfterSubscriptionNonpayment(): string
    {
        return $this->after_subscription_nonpayment;
    }

    public function setAfterSubscriptionNonpayment(string $after_subscription_nonpayment): void
    {
        $this->after_subscription_nonpayment = $after_subscription_nonpayment;
    }

    public function isSubscriptionDraftInvoices(): bool
    {
        return $this->subscription_draft_invoices;
    }

    public function setSubscriptionDraftInvoices(bool $subscription_draft_invoices): void
    {
        $this->subscription_draft_invoices = $subscription_draft_invoices;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        // Fixes parsing of timestamp using database timezone (UTC)
        return new CarbonImmutable($this->created_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
    }

    public function setCreatedAt(DateTimeInterface $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        if ($this->updated_at) {
            // Fixes parsing of timestamp using database timezone (UTC)
            return new CarbonImmutable($this->updated_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
        }

        return null;
    }

    public function setUpdatedAt(?DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
    }
}
