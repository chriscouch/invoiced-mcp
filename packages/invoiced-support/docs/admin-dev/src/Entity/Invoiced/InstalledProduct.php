<?php

namespace App\Entity\Invoiced;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'InstalledProducts')]
class InstalledProduct
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
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $installed_on;

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

    public function getInstalledOn(): DateTimeImmutable
    {
        return $this->installed_on;
    }

    public function setInstalledOn(DateTimeImmutable $installed_on): void
    {
        $this->installed_on = $installed_on;
    }
}
