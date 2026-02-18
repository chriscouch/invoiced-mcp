<?php

namespace App\Entity\CustomerAdmin;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cs_contract_overage_thresholds')]
class ContractOverageThreshold
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\ManyToOne(targetEntity: Contract::class, inversedBy: 'overageThresholds')]
    #[ORM\JoinColumn(nullable: false)]
    private Contract $contract;
    #[ORM\Column(type: 'string', length: 255)]
    private string $description;
    #[ORM\Column(type: 'integer')]
    private int $threshold;
    #[ORM\Column(type: 'string', length: 255)]
    private string $type;

    public function __toString(): string
    {
        return $this->description;
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function setThreshold(int $threshold): void
    {
        $this->threshold = $threshold;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
