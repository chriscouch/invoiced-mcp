<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'OverageCharges')]
class OverageCharge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'billedVolumes')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Column]
    private string $month;
    #[ORM\Column(type: 'string')]
    private string $dimension;
    #[ORM\Column(type: 'integer')]
    private int $quantity;
    #[ORM\Column(type: 'float')]
    private float $price;
    #[ORM\Column(type: 'float')]
    private float $total;
    #[ORM\Column(type: 'boolean')]
    private bool $billed;
    #[ORM\Column(type: 'string')]
    private string $billing_system;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $billing_system_id;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $failure_message;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
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

    public function setTenant(Company $tenant): self
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getMonth(): DateTimeInterface
    {
        return CarbonImmutable::createFromFormat('Ym', $this->month); /* @phpstan-ignore-line */
    }

    public function setMonth(DateTimeInterface $month): void
    {
        $this->month = $month->format('Ym');
    }

    public function getDimension(): string
    {
        return $this->dimension;
    }

    public function setDimension(string $dimension): void
    {
        $this->dimension = $dimension;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function setTotal(float $total): void
    {
        $this->total = $total;
    }

    public function isBilled(): bool
    {
        return $this->billed;
    }

    public function setBilled(bool $billed): void
    {
        $this->billed = $billed;
    }

    public function getBillingSystem(): string
    {
        return $this->billing_system;
    }

    public function setBillingSystem(string $billing_system): void
    {
        $this->billing_system = $billing_system;
    }

    public function getBillingSystemId(): ?string
    {
        return $this->billing_system_id;
    }

    public function setBillingSystemId(?string $billing_system_id): void
    {
        $this->billing_system_id = $billing_system_id;
    }

    public function getFailureMessage(): ?string
    {
        return $this->failure_message;
    }

    public function setFailureMessage(?string $failure_message): void
    {
        $this->failure_message = $failure_message;
    }
}
