<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'MarketingAttributions')]
class MarketingAttribution
{
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'marketingAttribution', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string')]
    private string $utm_campaign;
    #[ORM\Column(type: 'string')]
    private string $utm_source;
    #[ORM\Column(type: 'string')]
    private string $utm_content;
    #[ORM\Column(type: 'string')]
    private string $utm_medium;
    #[ORM\Column(type: 'string')]
    private string $utm_term;
    #[ORM\Column(type: 'string', name: '$initial_referrer')]
    private string $initial_referrer;
    #[ORM\Column(type: 'string', name: '$initial_referring_domain')]
    private string $initial_referring_domain;

    public function __toString(): string
    {
        return $this->tenant->getName().' Marketing Attribution';
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

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUtmCampaign(): string
    {
        return $this->utm_campaign;
    }

    public function setUtmCampaign(string $utm_campaign): void
    {
        $this->utm_campaign = $utm_campaign;
    }

    public function getUtmSource(): string
    {
        return $this->utm_source;
    }

    public function setUtmSource(string $utm_source): void
    {
        $this->utm_source = $utm_source;
    }

    public function getUtmContent(): string
    {
        return $this->utm_content;
    }

    public function setUtmContent(string $utm_content): void
    {
        $this->utm_content = $utm_content;
    }

    public function getUtmMedium(): string
    {
        return $this->utm_medium;
    }

    public function setUtmMedium(string $utm_medium): void
    {
        $this->utm_medium = $utm_medium;
    }

    public function getUtmTerm(): string
    {
        return $this->utm_term;
    }

    public function setUtmTerm(string $utm_term): void
    {
        $this->utm_term = $utm_term;
    }

    public function getInitialReferrer(): string
    {
        return $this->initial_referrer;
    }

    public function setInitialReferrer(string $initial_referrer): void
    {
        $this->initial_referrer = $initial_referrer;
    }

    public function getInitialReferringDomain(): string
    {
        return $this->initial_referring_domain;
    }

    public function setInitialReferringDomain(string $initial_referring_domain): void
    {
        $this->initial_referring_domain = $initial_referring_domain;
    }
}
