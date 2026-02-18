<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'MerchantAccounts')]
class MerchantAccount
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $gateway;
    #[ORM\Column(type: 'string', length: 255)]
    private string $gateway_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name;
    #[ORM\Column(type: 'integer')]
    private int $top_up_threshold_num_of_days;
    #[ORM\Column(type: 'boolean')]
    private bool $deleted;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $deleted_at;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\Invoiced\Company', inversedBy: 'merchantAccounts')]
    private Company $tenant;

    private ?string $key = '';
    private ?string $accessToken = '';

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenant_id = $tenant->getId();
    }

    public function __toString(): string
    {
        return $this->name ?? $this->gateway;
    }

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

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function setGateway(string $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function getGatewayId(): string
    {
        return $this->gateway_id;
    }

    public function setGatewayId(string $gateway_id): void
    {
        $this->gateway_id = $gateway_id;
    }

    public function getName(): ?string
    {
        return $this->name ?? 'Custom gateway';
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): void
    {
        $this->deleted = $deleted;
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

    public function getDeletedAt(): ?DateTimeInterface
    {
        if ($this->deleted_at) {
            // Fixes parsing of timestamp using database timezone (UTC)
            return new CarbonImmutable($this->deleted_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
        }

        return null;
    }

    public function setDeletedAt(?DateTimeInterface $deleted_at): void
    {
        $this->deleted_at = $deleted_at;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->key = $key;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getTopUpThresholdNumOfDays(): int
    {
        return $this->top_up_threshold_num_of_days;
    }

    public function setTopUpThresholdNumOfDays(int $top_up_threshold_num_of_days): void
    {
        $this->top_up_threshold_num_of_days = $top_up_threshold_num_of_days;
    }
}
