<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'CompanySamlSettings')]
class CompanySamlSettings
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $company_id;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = false;
    #[ORM\Column(type: 'boolean')]
    private bool $disable_non_sso = false;
    #[ORM\OneToOne(inversedBy: 'samlSettings', targetEntity: Company::class)]
    private Company $company;

    public function getCompanyId(): int
    {
        return $this->company_id;
    }

    public function setCompanyId(int $company_id): void
    {
        $this->company_id = $company_id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isDisableNonSso(): bool
    {
        return $this->disable_non_sso;
    }

    public function setDisableNonSso(bool $disable_non_sso): void
    {
        $this->disable_non_sso = $disable_non_sso;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }
}
