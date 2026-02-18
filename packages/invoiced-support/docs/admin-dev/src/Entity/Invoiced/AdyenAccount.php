<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AdyenAccounts')]
class AdyenAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\ManyToOne(targetEntity: Company::class)]
    private Company $tenant;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $split_configuration_id;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pricing_configuration_id;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $legal_entity_id;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $business_line_id;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $reference;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $account_holder_id;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $industry_code;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $terms_of_service_acceptance_date;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $terms_of_service_acceptance_ip;
    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $terms_of_service_acceptance_user;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $terms_of_service_acceptance_version;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $onboarding_started_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $activated_at;
    #[ORM\Column(type: 'boolean')]
    private bool $has_onboarding_problem;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statement_descriptor;

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenant_id = $tenant->getId();
    }

    public function __toString(): string
    {
        return 'Flywire Payments Account # '.$this->id;
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

    public function getCreatedAt(): DateTimeInterface
    {
        // Fixes parsing of timestamp using database timezone (UTC)
        return new CarbonImmutable($this->created_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
    }

    public function setCreatedAt(DateTimeInterface $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        if ($this->updated_at) {
            // Fixes parsing of timestamp using database timezone (UTC)
            return new CarbonImmutable($this->updated_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
        }

        return null;
    }

    public function setUpdatedAt(?DateTimeInterface $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function getSplitConfigurationId(): ?string
    {
        return $this->split_configuration_id;
    }

    public function setSplitConfigurationId(?string $split_configuration_id): void
    {
        $this->split_configuration_id = $split_configuration_id;
    }

    public function getLegalEntityId(): ?string
    {
        return $this->legal_entity_id;
    }

    public function setLegalEntityId(?string $legal_entity_id): void
    {
        $this->legal_entity_id = $legal_entity_id;
    }

    public function getBusinessLineId(): ?string
    {
        return $this->business_line_id;
    }

    public function setBusinessLineId(?string $business_line_id): void
    {
        $this->business_line_id = $business_line_id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getAccountHolderId(): ?string
    {
        return $this->account_holder_id;
    }

    public function setAccountHolderId(?string $account_holder_id): void
    {
        $this->account_holder_id = $account_holder_id;
    }

    public function getIndustryCode(): ?string
    {
        return $this->industry_code;
    }

    public function setIndustryCode(?string $industry_code): void
    {
        $this->industry_code = $industry_code;
    }

    public function getTermsOfServiceAcceptanceDate(): ?DateTimeInterface
    {
        return $this->terms_of_service_acceptance_date;
    }

    public function setTermsOfServiceAcceptanceDate(?DateTimeInterface $terms_of_service_acceptance_date): void
    {
        $this->terms_of_service_acceptance_date = $terms_of_service_acceptance_date;
    }

    public function getTermsOfServiceAcceptanceIp(): ?string
    {
        return $this->terms_of_service_acceptance_ip;
    }

    public function setTermsOfServiceAcceptanceIp(?string $terms_of_service_acceptance_ip): void
    {
        $this->terms_of_service_acceptance_ip = $terms_of_service_acceptance_ip;
    }

    public function getTermsOfServiceAcceptanceUser(): ?User
    {
        return $this->terms_of_service_acceptance_user;
    }

    public function setTermsOfServiceAcceptanceUser(?User $terms_of_service_acceptance_user): void
    {
        $this->terms_of_service_acceptance_user = $terms_of_service_acceptance_user;
    }

    public function getTermsOfServiceAcceptanceVersion(): ?string
    {
        return $this->terms_of_service_acceptance_version;
    }

    public function setTermsOfServiceAcceptanceVersion(?string $terms_of_service_acceptance_version): void
    {
        $this->terms_of_service_acceptance_version = $terms_of_service_acceptance_version;
    }

    public function getPricingConfigurationId(): ?int
    {
        return $this->pricing_configuration_id;
    }

    public function setPricingConfigurationId(?int $pricing_configuration_id): void
    {
        $this->pricing_configuration_id = $pricing_configuration_id;
    }

    public function getOnboardingStartedAt(): ?DateTimeInterface
    {
        return $this->onboarding_started_at;
    }

    public function setOnboardingStartedAt(?DateTimeInterface $onboarding_started_at): void
    {
        $this->onboarding_started_at = $onboarding_started_at;
    }

    public function getActivatedAt(): ?DateTimeInterface
    {
        return $this->activated_at;
    }

    public function setActivatedAt(?DateTimeInterface $activated_at): void
    {
        $this->activated_at = $activated_at;
    }

    public function isHasOnboardingProblem(): bool
    {
        return $this->has_onboarding_problem;
    }

    public function setHasOnboardingProblem(bool $has_onboarding_problem): void
    {
        $this->has_onboarding_problem = $has_onboarding_problem;
    }

    public function getStatementDescriptor(): ?string
    {
        return $this->statement_descriptor;
    }

    public function setStatementDescriptor(?string $statement_descriptor): void
    {
        $this->statement_descriptor = $statement_descriptor;
    }
}
