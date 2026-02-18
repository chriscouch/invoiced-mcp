<?php

namespace App\Entity\Invoiced;

use App\Enums\BillingSystem;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'BillingProfiles')]
class BillingProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string')]
    private string $name;
    #[ORM\Column(type: 'string', length: 8, nullable: true)]
    private ?string $billing_system = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $invoiced_customer = null;
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $stripe_customer = null;
    #[ORM\Column(nullable: true)]
    private bool $past_due = false;
    #[ORM\Column(type: 'string', length: 255)]
    private string $referred_by = '';
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $billing_interval = null;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\OneToMany(mappedBy: 'billingProfile', targetEntity: Company::class)]
    private Collection $tenants;
    #[ORM\OneToMany(mappedBy: 'billingProfile', targetEntity: UsagePricingPlan::class)]
    private Collection $usagePricingPlans;
    #[ORM\OneToMany(mappedBy: 'billingProfile', targetEntity: PurchasePageContext::class)]
    private Collection $purchasePages;
    #[ORM\OneToMany(mappedBy: 'billingProfile', targetEntity: CanceledCompany::class)]
    private Collection $canceledCompanies;

    public function __construct()
    {
        $this->tenants = new ArrayCollection();
        $this->usagePricingPlans = new ArrayCollection();
        $this->purchasePages = new ArrayCollection();
        $this->canceledCompanies = new ArrayCollection();
        $this->created_at = CarbonImmutable::now();
    }

    public function __toString(): string
    {
        return $this->name.' | # '.$this->id;
    }

    public function getBillingSystemId(): ?string
    {
        return match ($this->billing_system) {
            'invoiced', 'reseller' => $this->invoiced_customer,
            'stripe' => $this->stripe_customer,
            default => null,
        };
    }

    public function getBillingSystem(): ?string
    {
        return $this->billing_system;
    }

    public function getBillingSystemEnum(): BillingSystem
    {
        return BillingSystem::from((string) $this->billing_system);
    }

    public function getBillingSystemName(): string
    {
        return $this->getBillingSystemEnum()->getName();
    }

    public function setBillingSystem(?string $billing_system): void
    {
        $this->billing_system = $billing_system;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getInvoicedCustomer(): ?string
    {
        return $this->invoiced_customer;
    }

    public function setInvoicedCustomer(?string $invoiced_customer): void
    {
        $this->invoiced_customer = $invoiced_customer;
    }

    public function getStripeCustomer(): ?string
    {
        return $this->stripe_customer;
    }

    public function setStripeCustomer(?string $stripe_customer): void
    {
        $this->stripe_customer = $stripe_customer;
    }

    public function isPastDue(): bool
    {
        return $this->past_due;
    }

    public function setPastDue(bool $past_due): void
    {
        $this->past_due = $past_due;
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

    public function getReferredBy(): string
    {
        return $this->referred_by;
    }

    public function setReferredBy(?string $referred_by): void
    {
        $this->referred_by = (string) $referred_by;
    }

    public function getBillingInterval(): ?int
    {
        return $this->billing_interval;
    }

    public function setBillingInterval(?int $billing_interval): void
    {
        $this->billing_interval = $billing_interval;
    }

    public function getBillingIntervalName(): string
    {
        return match ($this->billing_interval) {
            default => 'None',
            1 => 'Monthly',
            2 => 'Yearly',
            3 => 'Quarterly',
            4 => 'Semiannually',
        };
    }

    /**
     * @return Collection|Company[]
     */
    public function getTenants(): Collection
    {
        return $this->tenants;
    }

    public function addTenant(Company $tenant): void
    {
        if (!$this->tenants->contains($tenant)) {
            $this->tenants[] = $tenant;
            $tenant->setBillingProfile($this);
        }
    }

    public function removeTenant(Company $tenant): void
    {
        $this->tenants->removeElement($tenant);
    }

    /**
     * @return Collection|UsagePricingPlan[]
     */
    public function getUsagePricingPlans(): Collection
    {
        return $this->usagePricingPlans;
    }

    /**
     * @return Collection|PurchasePageContext[]
     */
    public function getPurchasePages(): Collection
    {
        return $this->purchasePages;
    }

    /**
     * @return Collection|CanceledCompany[]
     */
    public function getCanceledCompanies(): Collection
    {
        return $this->canceledCompanies;
    }
}
