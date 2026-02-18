<?php

namespace App\Entity\Invoiced;

use App\Controller\Admin\PurchasePageCrudController;
use App\Utilities\ChangesetUtility;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\ObjectManager;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'PurchasePageContexts')]
#[ORM\HasLifecycleCallbacks]
class PurchasePageContext
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string')]
    private string $identifier;
    #[ORM\ManyToOne(targetEntity: BillingProfile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private BillingProfile $billingProfile;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $tenant;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $expiration_date;
    #[ORM\Column(type: 'integer')]
    private int $reason;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $sales_rep;
    #[ORM\Column(type: 'string')]
    private string $country;
    #[ORM\Column(type: 'boolean')]
    private bool $localized_pricing = false;
    #[ORM\Column(type: 'integer')]
    private int $payment_terms;
    #[ORM\Column(type: 'string')]
    private string $changeset;
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $activation_fee;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $note;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $completed_by_name;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $completed_by_ip;
    #[ORM\Column(type: 'datetime_immutable')]
    private ?DateTimeImmutable $completed_at;
    #[ORM\Column(type: 'datetime_immutable')]
    private ?DateTimeImmutable $last_viewed;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $created_at;
    #[ORM\Column(type: 'datetime_immutable')]
    private ?DateTimeImmutable $updated_at;
    private ObjectManager $objectManager;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getBillingProfile(): BillingProfile
    {
        return $this->billingProfile;
    }

    public function setBillingProfile(BillingProfile $billingProfile): void
    {
        $this->billingProfile = $billingProfile;
    }

    public function getTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setTenantId(int $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }

    public function getTenant(): ?Company
    {
        return $this->tenant;
    }

    public function setTenant(?Company $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getExpirationDate(): DateTimeImmutable
    {
        return $this->expiration_date;
    }

    public function isExpired(): bool
    {
        return CarbonImmutable::now()->startOfDay()->isAfter($this->expiration_date);
    }

    public function setExpirationDate(DateTimeInterface $expiration_date): void
    {
        $this->expiration_date = new CarbonImmutable($expiration_date);
    }

    public function getReason(): int
    {
        return $this->reason;
    }

    public function getFormattedReason(): string
    {
        return array_search($this->reason, PurchasePageCrudController::REASONS) ?: '';
    }

    public function setReason(int $reason): void
    {
        $this->reason = $reason;
    }

    public function getSalesRep(): ?string
    {
        return $this->sales_rep;
    }

    public function setSalesRep(?string $sales_rep): void
    {
        $this->sales_rep = $sales_rep;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getPaymentTerms(): int
    {
        return $this->payment_terms;
    }

    public function setPaymentTerms(int $payment_terms): void
    {
        $this->payment_terms = $payment_terms;
    }

    public function getChangeset(): string
    {
        return $this->changeset;
    }

    public function setChangeset(string $changeset): void
    {
        $this->changeset = $changeset;
    }

    public function getChangesetFormatted(): string
    {
        try {
            return ChangesetUtility::toFriendlyString((object) json_decode($this->changeset), $this->objectManager);
        } catch (Throwable) {
            return $this->changeset;
        }
    }

    public function getActivationFee(): ?float
    {
        return $this->activation_fee;
    }

    public function setActivationFee(?float $activation_fee): void
    {
        $this->activation_fee = $activation_fee;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getCompletedByName(): ?string
    {
        return $this->completed_by_name;
    }

    public function setCompletedByName(?string $completed_by_name): void
    {
        $this->completed_by_name = $completed_by_name;
    }

    public function getCompletedByIp(): ?string
    {
        return $this->completed_by_ip;
    }

    public function setCompletedByIp(?string $completed_by_ip): void
    {
        $this->completed_by_ip = $completed_by_ip;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        if ($this->completed_at) {
            // Fixes parsing of timestamp using database timezone (UTC)
            return new CarbonImmutable($this->completed_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
        }

        return null;
    }

    public function setCompletedAt(?DateTimeImmutable $completed_at): void
    {
        $this->completed_at = $completed_at;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        // Fixes parsing of timestamp using database timezone (UTC)
        return new CarbonImmutable($this->created_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
    }

    public function setCreatedAt(DateTimeImmutable $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        if ($this->updated_at) {
            // Fixes parsing of timestamp using database timezone (UTC)
            return new CarbonImmutable($this->updated_at->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
        }

        return null;
    }

    public function setUpdatedAt(?DateTimeImmutable $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function getLocalizedPricing(): bool
    {
        return $this->localized_pricing;
    }

    public function setLocalizedPricing(bool $localized_pricing): void
    {
        $this->localized_pricing = $localized_pricing;
    }

    public function getLastViewed(): ?DateTimeImmutable
    {
        if (!$this->last_viewed) {
            return $this->last_viewed;
        }

        // Fixes parsing of timestamp using database timezone (UTC)
        return new CarbonImmutable($this->last_viewed->format('Y-m-d H:i:s'), new CarbonTimeZone('UTC'));
    }

    public function setLastViewed(?DateTimeImmutable $last_viewed): void
    {
        $this->last_viewed = $last_viewed;
    }

    #[ORM\PostLoad]
    public function postLoad(LifecycleEventArgs $args): void
    {
        $this->objectManager = $args->getObjectManager();
    }
}
