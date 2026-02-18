<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'Companies')]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $nickname;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $creator_id;
    #[ORM\Column(type: 'boolean')]
    private bool $fraud;
    #[ORM\Column(type: 'string', length: 255)]
    private string $email;
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
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
    #[ORM\Column(type: 'text')]
    private string $address_extra;
    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;
    #[ORM\Column(type: 'boolean')]
    private bool $show_currency_code;
    #[ORM\Column(type: 'string', length: 10)]
    private string $date_format;
    #[ORM\Column(type: 'string', length: 50)]
    private string $time_zone;
    #[ORM\Column(type: 'string', length: 50)]
    private string $logo;
    #[ORM\Column(nullable: true)]
    private ?int $trial_started;
    #[ORM\Column(nullable: true)]
    private ?int $trial_ends;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $converted_at;
    #[ORM\Column(type: 'string', length: 255)]
    private string $converted_from;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $last_trial_reminder;
    #[ORM\Column(type: 'boolean')]
    private bool $canceled;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $canceled_at;
    #[ORM\Column(type: 'string', length: 255)]
    private string $canceled_reason;
    #[ORM\Column(type: 'string', length: 255)]
    private string $username;
    #[ORM\Column(type: 'string', length: 255)]
    private string $identifier;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $custom_domain;
    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $country;
    #[ORM\Column(type: 'string', length: 2)]
    private string $language;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $tax_id;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $last_overage_notification;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'boolean', nullable: true)]
    private bool $test_mode;
    #[ORM\Column(type: 'string', length: 255)]
    private string $highlight_color;
    #[ORM\Column(type: 'string', length: 296)]
    private string $sso_key_enc;
    #[ORM\Column(type: 'integer')]
    private int $search_last_reindexed;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $industry;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $website;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $phone;
    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    private ?User $creator;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: Member::class, mappedBy: 'tenant', orphanRemoval: true, cascade: ['persist', 'remove'])]
    private $companyMembers;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: MerchantAccount::class, mappedBy: 'tenant')]
    private $merchantAccounts;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: BilledVolume::class, mappedBy: 'tenant', orphanRemoval: true)]
    #[ORM\OrderBy(['month' => 'DESC'])]
    private $billedVolumes;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: CustomerVolume::class, mappedBy: 'tenant', orphanRemoval: true)]
    #[ORM\OrderBy(['month' => 'DESC'])]
    private $customerVolumes;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: InvoiceVolume::class, mappedBy: 'tenant', orphanRemoval: true)]
    #[ORM\OrderBy(['month' => 'DESC'])]
    private $invoiceVolumes;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: OverageCharge::class, mappedBy: 'tenant', orphanRemoval: true)]
    #[ORM\OrderBy(['month' => 'DESC'])]
    private $overageCharges;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: UsagePricingPlan::class, mappedBy: 'tenant', orphanRemoval: true)]
    private $usagePricingPlans;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: ProductPricingPlan::class, mappedBy: 'tenant', orphanRemoval: true)]
    #[ORM\OrderBy(['effective_date' => 'DESC'])]
    private $productPricingPlans;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: InstalledProduct::class, mappedBy: 'tenant', orphanRemoval: true)]
    private $installedProducts;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: Feature::class, mappedBy: 'tenant', orphanRemoval: true)]
    private $features;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: Quota::class, mappedBy: 'tenant', orphanRemoval: true)]
    private $quotas;
    #[ORM\OneToOne(targetEntity: MarketingAttribution::class, mappedBy: 'tenant')]
    private ?MarketingAttribution $marketingAttribution;
    #[ORM\ManyToOne(targetEntity: BillingProfile::class, inversedBy: 'tenants')]
    private ?BillingProfile $billingProfile;
    #[ORM\OneToOne(mappedBy: 'company', targetEntity: CompanySamlSettings::class)]
    private ?CompanySamlSettings $samlSettings;

    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: Dashboard::class, mappedBy: 'tenant', orphanRemoval: true)]
    private $dashboards;
    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: CompanyNote::class, mappedBy: 'tenant', orphanRemoval: true)]
    private $notes;

    public function __construct()
    {
        $this->companyMembers = new ArrayCollection();
        $this->merchantAccounts = new ArrayCollection();
        $this->billedVolumes = new ArrayCollection();
        $this->customerVolumes = new ArrayCollection();
        $this->invoiceVolumes = new ArrayCollection();
        $this->overageCharges = new ArrayCollection();
        $this->usagePricingPlans = new ArrayCollection();
        $this->productPricingPlans = new ArrayCollection();
        $this->installedProducts = new ArrayCollection();
        $this->features = new ArrayCollection();
        $this->quotas = new ArrayCollection();
        $this->dashboards = new ArrayCollection();
        $this->notes = new ArrayCollection();
    }

    public function __toString(): string
    {
        if (!$this->name) {
            return '#'.$this->id;
        }

        return $this->name.' | #'.$this->id;
    }

    public function getLogo(): ?string
    {
        if ($logo = $this->logo) {
            return 'https://logos.invoiced.com/'.$logo;
        }

        return null;
    }

    public function setLogo(string $logo): void
    {
        $this->logo = $logo;
    }

    public function getBillingSystemName(): string
    {
        if ($this->billingProfile) {
            return $this->billingProfile->getBillingSystemName();
        }

        return 'None';
    }

    public function getBillingSystemId(): ?string
    {
        if ($this->billingProfile) {
            return $this->billingProfile->getBillingSystemId();
        }

        return null;
    }

    public function getAddress(): string
    {
        $address = $this->getAddressObject();
        if (!$address) {
            return '';
        }

        try {
            $addressFormatRepository = new AddressFormatRepository();
            $countryRepository = new CountryRepository();
            $subdivisionRepository = new SubdivisionRepository();
            $formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository);

            return $formatter->format($address, ['html' => false]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function getAddressObject(): ?Address
    {
        if (!$this->country) {
            return null;
        }

        return (new Address())
            ->withAddressLine1((string) $this->address1)
            ->withAddressLine2((string) $this->address2)
            ->withLocality((string) $this->city)
            ->withAdministrativeArea((string) $this->state)
            ->withPostalCode((string) $this->postal_code)
            ->withCountryCode($this->country);
    }

    /**
     * Edited doctrine generated function.
     * Converts epoch time into DateTime.
     * Used to handle DateTime widget in
     * EasyAdmin forms.
     */
    public function getTrialStarted(): ?DateTimeInterface
    {
        if (!isset($this->trial_started)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->trial_started);
    }

    /**
     * Edited doctrine generated function.
     * Converts DateTime into epoch time.
     * Used to handle DateTime widget in
     * EasyAdmin forms.
     */
    public function setTrialStarted(?DateTimeInterface $trial_started_datetime): void
    {
        if (isset($trial_started_datetime)) {
            $this->trial_started = (int) $trial_started_datetime->format('U');
        } else {
            $this->trial_started = null;
        }
    }

    /**
     * Edited doctrine generated function.
     * Converts epoch time into DateTime.
     * Used to handle DateTime widget in
     * EasyAdmin forms.
     */
    public function getTrialEnds(): ?CarbonImmutable
    {
        if (!isset($this->trial_ends)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->trial_ends);
    }

    public function isExpiredTrial(): bool
    {
        $trialEnds = $this->getTrialEnds();
        if (!$trialEnds) {
            return false;
        }

        return $trialEnds->lessThan(CarbonImmutable::now());
    }

    /**
     * Edited doctrine generated function.
     * Converts DateTime into epoch time.
     * Used to handle DateTime widget in
     * EasyAdmin forms.
     */
    public function setTrialEnds(?DateTimeInterface $trial_ends_datetime): void
    {
        if (isset($trial_ends_datetime)) {
            $this->trial_ends = (int) $trial_ends_datetime->format('U');
        } else {
            $this->trial_ends = null;
        }
    }

    public function getDeletionAt(): ?int
    {
        if (!$this->canceled || !$this->canceled_at) {
            return null;
        }

        // accounts are deleted after 90 days
        return $this->canceled_at + 90 * 86400;
    }

    public function setCreator(?User $creator): void
    {
        $this->creator = $creator;

        // set (or unset) the owning side of the relation if necessary
        $newDefault_company = null === $creator ? null : $this;
        if ($creator && $newDefault_company !== $creator->getDefaultCompany()) {
            $creator->setDefaultCompany($newDefault_company);
        }
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    /**
     * Edited to only return members that aren't expired
     * and maximum of 5.
     *
     * @return Collection|Member[]
     */
    public function getCompanyMembers(): Collection
    {
        $currentMembers = new ArrayCollection();
        foreach ($this->companyMembers as $companyMember) {
            if (!$companyMember->isExpired()) {
                $currentMembers->add($companyMember);
            }

            if (count($currentMembers) >= 5) {
                break;
            }
        }

        return $currentMembers;
    }

    public function numUsers(): int
    {
        $n = 0;
        foreach ($this->companyMembers as $companyMember) {
            if (!$companyMember->isExpired()) {
                ++$n;
            }
        }

        return $n;
    }

    /**
     * @return Collection|InvoiceVolume[]
     */
    public function getInvoiceVolumes(): Collection
    {
        return $this->invoiceVolumes;
    }

    /**
     * @return Collection|BilledVolume[]
     */
    public function getBilledVolumes(): Collection
    {
        return $this->billedVolumes;
    }

    /**
     * @return Collection|CustomerVolume[]
     */
    public function getCustomerVolumes(): Collection
    {
        return $this->customerVolumes;
    }

    /**
     * @return Collection|OverageCharge[]
     */
    public function getOverageCharges(): Collection
    {
        return $this->overageCharges;
    }

    /**
     * @return Collection|UsagePricingPlan[]
     */
    public function getUsagePricingPlans(): Collection
    {
        return $this->usagePricingPlans;
    }

    /**
     * @return Collection|ProductPricingPlan[]
     */
    public function getProductPricingPlans(): Collection
    {
        return $this->productPricingPlans;
    }

    /**
     * @return ProductPricingPlan[]
     */
    public function getLatestProductPricingPlans(): array
    {
        $result = [];
        $checked = [];
        foreach ($this->productPricingPlans as $productPricingPlan) {
            $productId = $productPricingPlan->getProduct()->getId();
            if (!isset($checked[$productId])) {
                $checked[$productId] = true;
                $result[] = $productPricingPlan;
            }
        }

        return $result;
    }

    /**
     * @return Collection|InstalledProduct[]
     */
    public function getInstalledProducts(): Collection
    {
        // Sort by product name
        $products = $this->installedProducts->toArray();
        usort($products, fn (InstalledProduct $a, InstalledProduct $b) => strcmp($a->getProduct()->getName(), $b->getProduct()->getName()));

        return new ArrayCollection($products);
    }

    public function getRecentUsage(int $max = 5): array
    {
        $result = [];

        $invoiceVolumes = $this->getInvoiceVolumes()->slice(0, $max);
        $billedVolumes = $this->getBilledVolumes()->slice(0, $max);
        $customerVolumes = $this->getCustomerVolumes()->slice(0, $max);
        $overageCharges = $this->getOverageCharges()->slice(0, $max);

        // build the most recent N months based on invoice volumes
        // and find the usage entries that are related to it
        $dateFormat = 'Ym';
        foreach ($invoiceVolumes as $invoiceVolume) {
            $month = $invoiceVolume->getMonth()->format($dateFormat);
            $result[$month] = [
                'month' => $invoiceVolume->getMonth(),
                'invoiceVolume' => $invoiceVolume,
                'billedVolume' => null,
                'customerVolume' => null,
                'overageCharge' => null,
            ];
        }

        foreach ($customerVolumes as $customerVolume) {
            $month = $customerVolume->getMonth()->format($dateFormat);
            if (isset($result[$month])) {
                $result[$month]['customerVolume'] = $customerVolume;
            }
        }

        foreach ($billedVolumes as $billedVolume) {
            $month = $billedVolume->getMonth()->format($dateFormat);
            if (isset($result[$month])) {
                $result[$month]['billedVolume'] = $billedVolume;
            }
        }

        foreach ($overageCharges as $overageCharge) {
            $month = $overageCharge->getMonth()->format($dateFormat);
            if (isset($result[$month])) {
                $result[$month]['overageCharge'] = $overageCharge;
            }
        }

        return $result;
    }

    public function viewCustomerPortal(): ?string
    {
        if (!$this->username) {
            return null;
        }

        if ($this->custom_domain) {
            return 'https://'.$this->custom_domain;
        }

        $url = (string) getenv('BILLING_PORTAL_URL');

        return str_replace('{username}', $this->username, $url);
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

    public function getCreatorId(): ?int
    {
        return $this->creator_id;
    }

    public function setCreatorId(?int $creator_id): void
    {
        $this->creator_id = $creator_id;
    }

    public function isFraud(): bool
    {
        return $this->fraud;
    }

    public function setFraud(bool $fraud): void
    {
        $this->fraud = $fraud;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
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

    public function getAddressExtra(): string
    {
        return $this->address_extra;
    }

    public function setAddressExtra(string $address_extra): void
    {
        $this->address_extra = $address_extra;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function isShowCurrencyCode(): bool
    {
        return $this->show_currency_code;
    }

    public function setShowCurrencyCode(bool $show_currency_code): void
    {
        $this->show_currency_code = $show_currency_code;
    }

    public function getDateFormat(): string
    {
        return $this->date_format;
    }

    public function setDateFormat(string $date_format): void
    {
        $this->date_format = $date_format;
    }

    public function getTimeZone(): string
    {
        return $this->time_zone;
    }

    public function setTimeZone(string $time_zone): void
    {
        $this->time_zone = $time_zone;
    }

    public function getConvertedAt(): ?int
    {
        return $this->converted_at;
    }

    public function setConvertedAt(?int $converted_at): void
    {
        $this->converted_at = $converted_at;
    }

    public function getConvertedFrom(): string
    {
        return $this->converted_from;
    }

    public function setConvertedFrom(string $converted_from): void
    {
        $this->converted_from = $converted_from;
    }

    public function getLastTrialReminder(): ?int
    {
        return $this->last_trial_reminder;
    }

    public function setLastTrialReminder(?int $last_trial_reminder): void
    {
        $this->last_trial_reminder = $last_trial_reminder;
    }

    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function setCanceled(bool $canceled): void
    {
        $this->canceled = $canceled;
    }

    /**
     * Edited doctrine generated function.
     * Converts epoch time into DateTime.
     * Used to handle DateTime widget in
     * EasyAdmin forms.
     */
    public function getCanceledAt(): ?CarbonImmutable
    {
        if (!isset($this->canceled_at)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($this->canceled_at);
    }

    /**
     * Edited doctrine generated function.
     * Converts DateTime into epoch time.
     * Used to handle DateTime widget in
     * EasyAdmin forms.
     */
    public function setCanceledAt(?DateTimeInterface $canceled_at): void
    {
        if (isset($canceled_at)) {
            $this->canceled_at = (int) $canceled_at->format('U');
        } else {
            $this->canceled_at = null;
        }
    }

    public function getCanceledReason(): string
    {
        return $this->canceled_reason;
    }

    public function setCanceledReason(string $canceled_reason): void
    {
        $this->canceled_reason = $canceled_reason;
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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getTaxId(): ?string
    {
        return $this->tax_id;
    }

    public function setTaxId(?string $tax_id): void
    {
        $this->tax_id = $tax_id;
    }

    public function getLastOverageNotification(): ?int
    {
        return $this->last_overage_notification;
    }

    public function setLastOverageNotification(?int $last_overage_notification): void
    {
        $this->last_overage_notification = $last_overage_notification;
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

    public function isTestMode(): bool
    {
        return $this->test_mode;
    }

    public function setTestMode(bool $test_mode): void
    {
        $this->test_mode = $test_mode;
    }

    public function getHighlightColor(): string
    {
        return $this->highlight_color;
    }

    public function setHighlightColor(string $highlight_color): void
    {
        $this->highlight_color = $highlight_color;
    }

    public function getSsoKeyEnc(): string
    {
        return $this->sso_key_enc;
    }

    public function setSsoKeyEnc(string $sso_key_enc): void
    {
        $this->sso_key_enc = $sso_key_enc;
    }

    public function getSearchLastReindexed(): int
    {
        return $this->search_last_reindexed;
    }

    public function setSearchLastReindexed(int $search_last_reindexed): void
    {
        $this->search_last_reindexed = $search_last_reindexed;
    }

    public function getMerchantAccounts(): Collection
    {
        return $this->merchantAccounts;
    }

    /**
     * Gets all merchant accounts except those
     * which are deleted and have gateway_id=0.
     */
    public function getActiveMerchantAccounts(): Collection
    {
        $result = new ArrayCollection();
        /** @var MerchantAccount $merchantAccount */
        foreach ($this->merchantAccounts as $merchantAccount) {
            if ($merchantAccount->getDeleted() && !$merchantAccount->getGatewayId()) {
                continue;
            }

            $result->add($merchantAccount);
        }

        return $result;
    }

    public function setMerchantAccounts(Collection $merchantAccounts): void
    {
        $this->merchantAccounts = $merchantAccounts;
    }

    public function getFeatures(): Collection
    {
        return $this->features;
    }

    public function setFeatures(Collection $features): void
    {
        $this->features = $features;
    }

    public function getQuotas(): Collection
    {
        return $this->quotas;
    }

    public function setQuotas(Collection $quotas): void
    {
        $this->quotas = $quotas;
    }

    public function getDashboards(): ArrayCollection|Collection
    {
        return $this->dashboards;
    }

    public function setDashboards(ArrayCollection|Collection $dashboards): void
    {
        $this->dashboards = $dashboards;
    }

    public function getNotes(): ArrayCollection|Collection
    {
        return $this->notes;
    }

    public function setNotes(ArrayCollection|Collection $notes): void
    {
        $this->notes = $notes;
    }

    public function getMarketingAttribution(): ?MarketingAttribution
    {
        return $this->marketingAttribution;
    }

    public function setMarketingAttribution(?MarketingAttribution $marketingAttribution): void
    {
        $this->marketingAttribution = $marketingAttribution;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(?string $industry): void
    {
        $this->industry = $industry;
    }

    public function getBillingProfile(): ?BillingProfile
    {
        return $this->billingProfile;
    }

    public function setBillingProfile(?BillingProfile $billingProfile): void
    {
        $this->billingProfile = $billingProfile;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): void
    {
        $this->nickname = $nickname;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getSamlSettings(): CompanySamlSettings
    {
        return $this->samlSettings ?? new CompanySamlSettings();
    }

    public function setSamlSettings(?CompanySamlSettings $samlSettings): void
    {
        $this->samlSettings = $samlSettings;
    }
}
