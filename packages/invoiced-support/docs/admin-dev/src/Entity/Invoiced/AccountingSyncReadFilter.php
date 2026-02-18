<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AccountingSyncReadFilters')]
class AccountingSyncReadFilter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\OneToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'integer')]
    private int $integration;
    #[ORM\Column(type: 'string')]
    private string $object_type;
    #[ORM\Column(type: 'text')]
    private string $formula;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    public function __toString(): string
    {
        return $this->tenant->getName().' / '.$this->object_type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
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

    public function getIntegration(): int
    {
        return $this->integration;
    }

    public function setIntegration(int $integration): void
    {
        $this->integration = $integration;
    }

    public function getObjectType(): string
    {
        return $this->object_type;
    }

    public function setObjectType(string $object_type): void
    {
        $this->object_type = $object_type;
    }

    public function getFormula(): string
    {
        return $this->formula;
    }

    public function setFormula(string $formula): void
    {
        $this->formula = $formula;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
