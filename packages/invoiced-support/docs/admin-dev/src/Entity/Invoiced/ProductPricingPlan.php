<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'ProductPricingPlans')]
class ProductPricingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Product')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column(type: 'float')]
    private float $price;
    #[ORM\Column(type: 'boolean')]
    private bool $annual;
    #[ORM\Column(type: 'boolean')]
    private bool $custom_pricing;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $effective_date;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $posted_on;

    public function __construct()
    {
        $this->posted_on = CarbonImmutable::now();
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
        $this->tenant_id = $tenant->getId();
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function isAnnual(): bool
    {
        return $this->annual;
    }

    public function setAnnual(bool $annual): void
    {
        $this->annual = $annual;
    }

    public function isCustomPricing(): bool
    {
        return $this->custom_pricing;
    }

    public function setCustomPricing(bool $custom_pricing): void
    {
        $this->custom_pricing = $custom_pricing;
    }

    public function getEffectiveDate(): DateTimeImmutable
    {
        return $this->effective_date;
    }

    public function setEffectiveDate(DateTimeImmutable $effective_date): void
    {
        $this->effective_date = $effective_date;
    }

    public function getPostedOn(): DateTimeImmutable
    {
        return $this->posted_on;
    }

    public function setPostedOn(DateTimeImmutable $posted_on): void
    {
        $this->posted_on = $posted_on;
    }
}
