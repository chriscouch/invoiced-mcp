<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'Quotas')]
#[UniqueEntity(['tenant_id', 'quota_type'])]
class Quota
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'integer')]
    private int $quota_type;
    #[ORM\Column(name: '`limit`', type: 'integer')]
    private int $limit;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'quotas')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function getName(): string
    {
        return match ($this->quota_type) {
            1 => 'Users',
            2 => 'Transactions per Day',
            3 => 'New Company Limit',
            4 => 'Max Open Network Invitations',
            5 => 'Max Document Versions',
            6 => 'Vendor Pay Daily Limit',
            7 => 'Customer Email Daily Limit',
            default => 'Unknown',
        };
    }

    public function __toString(): string
    {
        return $this->getName().' Quota';
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

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenant_id = $tenant->getId();
    }

    public function getQuotaType(): int
    {
        return $this->quota_type;
    }

    public function setQuotaType(int $quota_type): void
    {
        $this->quota_type = $quota_type;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}
