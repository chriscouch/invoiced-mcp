<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'Templates')]
#[UniqueEntity(['tenant_id', 'filename'])]
class Template
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
    #[ORM\Column(type: 'string')]
    private string $filename;
    #[ORM\Column(type: 'text')]
    private string $content;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;

    public function __construct()
    {
        $this->created_at = CarbonImmutable::now();
    }

    public function __toString(): string
    {
        return $this->tenant_id.'/'.$this->filename;
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content ?? '';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTimeInterface $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
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
}
