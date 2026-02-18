<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AccountingSyncFieldMappings')]
class AccountingSyncFieldMapping
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
    #[ORM\Column(type: 'integer')]
    private int $direction;
    #[ORM\Column(type: 'string')]
    private string $object_type;
    #[ORM\Column(type: 'string')]
    private string $source_field;
    #[ORM\Column(type: 'string')]
    private string $destination_field;
    #[ORM\Column(type: 'string')]
    private string $data_type = 'string';
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $value = null;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    public function __toString(): string
    {
        return $this->tenant->getName().' / '.$this->object_type.' / '.$this->source_field.' -> '.$this->destination_field;
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

    public function getSourceField(): string
    {
        return $this->source_field;
    }

    public function setSourceField(string $source_field): void
    {
        $this->source_field = $source_field;
    }

    public function getDestinationField(): string
    {
        return $this->destination_field;
    }

    public function setDestinationField(string $destination_field): void
    {
        $this->destination_field = $destination_field;
    }

    public function getDataType(): string
    {
        return $this->data_type;
    }

    public function setDataType(string $data_type): void
    {
        $this->data_type = $data_type;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getDirection(): int
    {
        return $this->direction;
    }

    public function setDirection(int $direction): void
    {
        $this->direction = $direction;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
