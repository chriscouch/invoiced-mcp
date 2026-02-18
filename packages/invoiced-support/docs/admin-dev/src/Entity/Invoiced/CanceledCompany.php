<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'CanceledCompanies')]
class CanceledCompany
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;
    #[ORM\Column(type: 'string', length: 255)]
    private string $email;
    #[ORM\Column(type: 'string', length: 255)]
    private string $username;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $custom_domain;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $type;
    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    private ?string $address1;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address2;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $city;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $state;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $postal_code;
    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $country;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $tax_id;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address_extra;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $creator_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripe_customer;
    #[ORM\Column(type: 'boolean')]
    private bool $past_due;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $canceled_at;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $trial_started;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $converted_at;
    #[ORM\Column(type: 'string', length: 255)]
    private string $converted_from;
    #[ORM\Column(type: 'string', length: 255)]
    private string $canceled_reason;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $referred_by;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\User', cascade: ['persist', 'remove'])]
    private ?User $creator;
    #[ORM\ManyToOne(targetEntity: BillingProfile::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?BillingProfile $billingProfile;

    public function __toString(): string
    {
        return $this->name.' | '.'Canceled Company: #'.$this->id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getCustomDomain(): ?string
    {
        return $this->custom_domain;
    }

    public function setCustomDomain(?string $custom_domain): void
    {
        $this->custom_domain = $custom_domain;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    public function setAddress1(?string $address1): void
    {
        $this->address1 = $address1;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function setAddress2(?string $address2): void
    {
        $this->address2 = $address2;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }

    public function getPostalCode(): ?string
    {
        return $this->postal_code;
    }

    public function setPostalCode(?string $postal_code): void
    {
        $this->postal_code = $postal_code;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    public function getTaxId(): ?string
    {
        return $this->tax_id;
    }

    public function setTaxId(?string $tax_id): void
    {
        $this->tax_id = $tax_id;
    }

    public function getAddressExtra(): ?string
    {
        return $this->address_extra;
    }

    public function setAddressExtra(?string $address_extra): void
    {
        $this->address_extra = $address_extra;
    }

    public function getCreatorId(): ?int
    {
        return $this->creator_id;
    }

    public function setCreatorId(?int $creator_id): void
    {
        $this->creator_id = $creator_id;
    }

    public function getStripeCustomer(): ?string
    {
        return $this->stripe_customer;
    }

    public function setStripeCustomer(?string $stripe_customer): void
    {
        $this->stripe_customer = $stripe_customer;
    }

    public function isPastDue(): bool
    {
        return $this->past_due;
    }

    public function setPastDue(bool $past_due): void
    {
        $this->past_due = $past_due;
    }

    public function getCanceledAt(): ?DateTimeInterface
    {
        if (!$this->canceled_at) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->canceled_at);
    }

    public function setCanceledAt(?int $canceled_at): void
    {
        $this->canceled_at = $canceled_at;
    }

    public function setTrialStarted(?int $trial_started): void
    {
        $this->trial_started = $trial_started;
    }

    public function setConvertedAt(?int $converted_at): void
    {
        $this->converted_at = $converted_at;
    }

    public function getTrialStarted(): ?DateTimeInterface
    {
        if (!$this->trial_started) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->trial_started);
    }

    public function getConvertedAt(): ?DateTimeInterface
    {
        if (!$this->converted_at) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->converted_at);
    }

    public function getConvertedFrom(): string
    {
        return $this->converted_from;
    }

    public function setConvertedFrom(string $converted_from): void
    {
        $this->converted_from = $converted_from;
    }

    public function getCanceledReason(): string
    {
        return $this->canceled_reason;
    }

    public function setCanceledReason(string $canceled_reason): void
    {
        $this->canceled_reason = $canceled_reason;
    }

    public function getReferredBy(): ?string
    {
        return $this->referred_by;
    }

    public function setReferredBy(?string $referred_by): void
    {
        $this->referred_by = $referred_by;
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

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): void
    {
        $this->creator = $creator;
    }

    public function getBillingProfile(): ?BillingProfile
    {
        return $this->billingProfile;
    }

    public function setBillingProfile(?BillingProfile $billingProfile): void
    {
        $this->billingProfile = $billingProfile;
    }
}
