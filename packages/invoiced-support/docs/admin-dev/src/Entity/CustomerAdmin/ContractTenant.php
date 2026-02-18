<?php

namespace App\Entity\CustomerAdmin;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cs_contract_tenants')]
class ContractTenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'tenants')]
    #[ORM\JoinColumn(nullable: false)]
    private Contract $contract;
    #[ORM\Column(type: 'integer')]
    private int $tenantId;

    public function __toString(): string
    {
        return (string) $this->tenantId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContract(): Contract
    {
        return $this->contract;
    }

    public function setContract(Contract $contract): void
    {
        $this->contract = $contract;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function setTenantId(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}
