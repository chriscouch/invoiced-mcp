<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'Features')]
#[UniqueEntity(['tenant_id', 'feature'])]
class Feature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $feature;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'features')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function __toString(): string
    {
        return $this->feature;
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

    public function getFeature(): string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): void
    {
        $this->feature = $feature;
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getFeatureChoice(): ?string
    {
        return null;
    }

    public function setFeatureChoice(?string $featureChoice): void
    {
        if ($featureChoice) {
            $this->feature = $featureChoice;
        }
    }
}
