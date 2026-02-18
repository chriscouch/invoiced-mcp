<?php

namespace App\Entity\CustomerAdmin;

use App\Enums\ContractRenewalStatus;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cs_contracts')]
class Contract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $customer;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeInterface $startDate;
    #[ORM\Column(type: 'integer')]
    private int $durationMonths;
    #[ORM\Column(type: 'boolean')]
    private bool $needsReview = false;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeInterface $endDate;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeInterface $createdAt;
    #[ORM\Column(type: 'integer')]
    private int $billingProfileId;
    #[ORM\Column(type: 'string', length: 50)]
    private string $renewalStatus;
    #[ORM\OneToMany(targetEntity: 'App\Entity\CustomerAdmin\Order', mappedBy: 'contract', orphanRemoval: true, cascade: ['persist'])]
    private Collection $orders;
    #[ORM\OneToMany(targetEntity: ContractTenant::class, mappedBy: 'contract', orphanRemoval: true, cascade: ['persist'])]
    private Collection $tenants;
    #[ORM\OneToMany(targetEntity: ContractOverageThreshold::class, mappedBy: 'contract', orphanRemoval: true, cascade: ['persist'])]
    private Collection $overageThresholds;

    public function __construct()
    {
        $this->createdAt = CarbonImmutable::now();
        $this->orders = new ArrayCollection();
        $this->tenants = new ArrayCollection();
        $this->overageThresholds = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getFormattedId().' | '.$this->customer;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFormattedId(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getCustomer(): string
    {
        return $this->customer;
    }

    public function setCustomer(string $customer): void
    {
        $this->customer = $customer;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getStartDate(): DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeInterface $startDate): void
    {
        $this->startDate = new CarbonImmutable($startDate);
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): void
    {
        $this->durationMonths = $durationMonths;
        // recalculate the end date to be the last day of the period
        $this->endDate = (new CarbonImmutable($this->startDate))
            ->addMonths($durationMonths)
            ->subDay();
    }

    public function getEndDate(): DateTimeInterface
    {
        return $this->endDate;
    }

    /**
     * @return Collection|Order[]
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    /**
     * @return Collection|ContractTenant[]
     */
    public function getTenants(): Collection
    {
        return $this->tenants;
    }

    public function addTenantId(int $tenantId): void
    {
        $tenant = new ContractTenant();
        $tenant->setContract($this);
        $tenant->setTenantId($tenantId);
        $this->addTenant($tenant);
    }

    public function addTenant(ContractTenant $tenant): void
    {
        if (!$this->tenants->contains($tenant)) {
            $this->tenants[] = $tenant;
            $tenant->setContract($this);
        }
    }

    public function removeTenant(ContractTenant $tenant): void
    {
        $this->tenants->removeElement($tenant);
    }

    /**
     * @return Collection|ContractOverageThreshold[]
     */
    public function getOverageThresholds(): Collection
    {
        return $this->overageThresholds;
    }

    public function addOverageThreshold(ContractOverageThreshold $overageQuota): void
    {
        if (!$this->overageThresholds->contains($overageQuota)) {
            $this->overageThresholds[] = $overageQuota;
            $overageQuota->setContract($this);
        }
    }

    public function removeOverageThreshold(ContractOverageThreshold $overageQuota): void
    {
        $this->overageThresholds->removeElement($overageQuota);
    }

    public function getStatus(): string
    {
        if ($this->needsReview) {
            return 'Needs Review';
        }

        $now = CarbonImmutable::now();
        $endDate = new CarbonImmutable($this->endDate);
        if ($endDate->isBefore($now)) {
            return 'Expired';
        }

        $startDate = new CarbonImmutable($this->startDate);
        if ($startDate->isAfter($now)) {
            return 'Not Started';
        }

        return 'Active';
    }

    public function getRenewalStatus(): string
    {
        return $this->renewalStatus;
    }

    public function setRenewalStatus(string $renewalStatus): void
    {
        $this->renewalStatus = $renewalStatus;
    }

    public function getRenewalStatusEnum(): ContractRenewalStatus
    {
        return ContractRenewalStatus::from($this->renewalStatus);
    }

    public function getBillingProfileId(): int
    {
        return $this->billingProfileId;
    }

    public function setBillingProfileId(int $billingProfileId): void
    {
        $this->billingProfileId = $billingProfileId;
    }

    public function isNeedsReview(): bool
    {
        return $this->needsReview;
    }

    public function setNeedsReview(bool $needsReview): void
    {
        $this->needsReview = $needsReview;
    }
}
