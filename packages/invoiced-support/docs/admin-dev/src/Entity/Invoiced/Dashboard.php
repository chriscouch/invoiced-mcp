<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'Dashboards')]
#[UniqueEntity(['tenant_id', 'name'])]
class Dashboard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;
    #[ORM\Column(type: 'string')]
    private string $definition;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'dashboards')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function __toString(): string
    {
        return $this->name;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function setDefinition(string $definition): void
    {
        $this->definition = $definition;
    }
}
