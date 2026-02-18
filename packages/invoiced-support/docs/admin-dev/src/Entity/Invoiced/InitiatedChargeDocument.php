<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'InitiatedChargeDocuments')]
class InitiatedChargeDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'integer')]
    private string $initiated_charge_id;
    #[ORM\Column(type: 'smallint')]
    private int $document_type;
    #[ORM\Column(type: 'integer')]
    private int $document_id;
    #[ORM\Column(type: 'decimal')]
    private float $amount;

    #[ORM\ManyToOne(targetEntity: InitiatedCharge::class, inversedBy: 'initiatedChargeDocuments')]
    #[ORM\JoinColumn(nullable: false)]
    private InitiatedCharge $initiated_charge;

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

    public function getInitiatedChargeId(): string
    {
        return $this->initiated_charge_id;
    }

    public function setInitiatedChargeId(string $initiated_charge_id): void
    {
        $this->initiated_charge_id = $initiated_charge_id;
    }

    public function isDocumentType(): int
    {
        return $this->document_type;
    }

    public function setDocumentType(int $document_type): void
    {
        $this->document_type = $document_type;
    }

    public function getDocumentId(): int
    {
        return $this->document_id;
    }

    public function setDocumentId(int $document_id): void
    {
        $this->document_id = $document_id;
    }

    public function getInitiatedCharge(): InitiatedCharge
    {
        return $this->initiated_charge;
    }

    public function setInitiatedCharge(InitiatedCharge $initiated_charge): void
    {
        $this->initiated_charge = $initiated_charge;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function __toString(): string
    {
        $documentName = match ($this->document_type) {
            1 => 'Convenience Fee',
            2 => 'Credit',
            3 => 'Credit Note # '.$this->document_id,
            4 => 'Estimate # '.$this->document_id,
            5 => 'Invoice # '.$this->document_id,
            default => $this->document_id ? 'Unknown # '.$this->document_id : 'None',
        };

        return $documentName.' - '.number_format($this->amount, 2);
    }
}
