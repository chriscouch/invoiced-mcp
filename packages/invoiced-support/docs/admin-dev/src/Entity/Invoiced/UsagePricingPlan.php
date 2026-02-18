<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'UsagePricingPlans')]
class UsagePricingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private ?int $billing_profile_id = null;
    #[ORM\ManyToOne(targetEntity: BillingProfile::class)]
    private ?BillingProfile $billingProfile;
    #[ORM\Column(type: 'integer')]
    private ?int $tenant_id = null;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'usagePricingPlans')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $tenant;
    #[ORM\Column(type: 'integer')]
    private int $usage_type;
    #[ORM\Column(type: 'integer')]
    private int $threshold;
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column(type: 'float')]
    private float $unit_price;

    #[Assert\Callback]
    public function validateBusinessRules(ExecutionContextInterface $context): void
    {
        if ($this->tenant_id && $this->billing_profile_id) {
            $context->buildViolation('You cannot set both the billing profile and tenant.')
                ->atPath('tenant_id') // or 'billing_profile_id'
                ->addViolation();
        }

        if ($this->billing_profile_id && $this->usage_type !== 5) { // 5 = Entities
            $context->buildViolation('You cannot set this usage type on a billing profile.')
                ->atPath('usage_type')
                ->addViolation();
        }

        if ($this->tenant_id && $this->usage_type === 5) { // 5 = Entities
            $context->buildViolation('You cannot set this usage type on a tenant.')
                ->atPath('usage_type')
                ->addViolation();
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTenantId(): ?int
    {
        return $this->tenant_id;
    }

    public function setTenantId(?int $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }

    public function getTenant(): ?Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenant_id = $tenant->getId();
    }

    public function getBillingProfileId(): ?int
    {
        return $this->billing_profile_id;
    }

    public function setBillingProfileId(int $billing_profile_id): void
    {
        $this->billing_profile_id = $billing_profile_id;
    }

    public function getBillingProfile(): ?BillingProfile
    {
        return $this->billingProfile;
    }

    public function setBillingProfile(BillingProfile $billingProfile): void
    {
        $this->billingProfile = $billingProfile;
        $this->billing_profile_id = $billingProfile->getId();
    }

    public function getUsageType(): int
    {
        return $this->usage_type;
    }

    public function setUsageType(int $usage_type): void
    {
        $this->usage_type = $usage_type;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function setThreshold(int $threshold): void
    {
        $this->threshold = $threshold;
    }

    public function getUnitPrice(): float
    {
        return $this->unit_price;
    }

    public function setUnitPrice(float $unit_price): void
    {
        $this->unit_price = $unit_price;
    }

    public function getUsageTypeName(): string
    {
        return match ($this->usage_type) {
            1 => 'Invoices/Month',
            2 => 'Customers/Month',
            3 => 'Users',
            4 => 'Money Billed/Month',
            5 => 'Entities',
            default => 'Unknown',
        };
    }
}
