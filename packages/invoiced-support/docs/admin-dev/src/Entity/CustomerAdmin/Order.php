<?php

namespace App\Entity\CustomerAdmin;

use App\Controller\Admin\BillingProfileCrudController;
use App\Enums\ChangeOrderType;
use App\Enums\OrderType;
use App\Repository\CustomerAdmin\OrderRepository;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @Vich\Uploadable
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'cs_orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[Assert\Length(min: 1)]
    #[ORM\Column(type: 'string', length: 50)]
    private string $type;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeInterface $date;
    #[ORM\Column(type: 'string', length: 255)]
    private string $customer = '';
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address1;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address2 = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $city;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $state;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $postal_code;
    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $country;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $billing_email;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $billing_phone;
    #[ORM\Column(type: 'string', length: 255)]
    private string $sales_rep;
    #[ORM\Column(type: 'date_immutable')]
    private DateTimeInterface $start_date;
    #[ORM\Column(type: 'decimal', scale: 2, precision: 10, nullable: true)]
    private ?string $sow_amount = null;
    #[ORM\Column(type: 'decimal', scale: 2, precision: 10, nullable: true)]
    private ?string $recurring_amount;
    #[ORM\Column(type: 'string', length: 15, nullable: true)]
    private ?string $recurring_interval;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $date_fulfilled;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fulfilled_by;
    #[ORM\Column(type: 'string', length: 50)]
    private string $status;
    #[ORM\Column(type: 'boolean')]
    private bool $billing_change = false;
    #[ORM\Column(type: 'boolean')]
    private bool $entitlement_change = false;
    #[ORM\Column(type: 'string', length: 50)]
    private string $change_order_type = '';
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $attachment = null;
    /**
     * @Vich\UploadableField(mapping="orders", fileNameProperty="attachment", originalName="attachment_name")
     */
    private ?File $attachment_file = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $attachment_name;
    #[ORM\OneToOne(targetEntity: '\App\Entity\CustomerAdmin\NewAccount', cascade: ['persist', 'remove'])]
    private ?NewAccount $newAccount = null;
    #[ORM\ManyToOne(targetEntity: '\App\Entity\CustomerAdmin\Contract', inversedBy: 'orders')]
    private ?Contract $contract = null;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $createdTenant;
    #[ORM\Column(type: 'integer')]
    private int $billingProfileId;
    #[ORM\Column(type: 'json')]
    private mixed $productPricingPlans = [];
    #[ORM\Column(type: 'json')]
    private mixed $usagePricingPlans = [];
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $newUserCount = null;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $billingInterval;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $tenantId = null;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $changeset = null;

    public function __construct()
    {
        $this->date = CarbonImmutable::now();
        $this->start_date = CarbonImmutable::now();
        $this->status = 'open';
    }

    public function __toString(): string
    {
        return $this->getFormattedId().' | '.$this->customer;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFormattedId(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFormattedType(): string
    {
        return $this->getTypeEnum()->getName();
    }

    public function getTypeEnum(): OrderType
    {
        return OrderType::from($this->type);
    }

    public function getChangeOrderTypeEnum(): ChangeOrderType
    {
        return ChangeOrderType::from($this->change_order_type);
    }

    public function getFormattedChangeOrderType(): string
    {
        return $this->getChangeOrderTypeEnum()->getName();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDate(): CarbonImmutable
    {
        if (!($this->date instanceof CarbonImmutable)) {
            $this->date = new CarbonImmutable($this->date);
        }

        return $this->date;
    }

    public function setDate(DateTimeInterface $date): void
    {
        $this->date = new CarbonImmutable($date);
    }

    public function getStartDate(): CarbonImmutable
    {
        if (!($this->start_date instanceof CarbonImmutable)) {
            $this->start_date = new CarbonImmutable($this->start_date);
        }

        return $this->start_date;
    }

    public function setStartDate(DateTimeInterface $date): void
    {
        $this->start_date = new CarbonImmutable($date);
    }

    public function getCustomer(): string
    {
        return $this->customer;
    }

    public function setCustomer(string $customer): void
    {
        $this->customer = $customer;
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

    public function getBillingEmail(): ?string
    {
        return $this->billing_email;
    }

    public function setBillingEmail(?string $billing_email): void
    {
        $this->billing_email = $billing_email;
    }

    public function getBillingPhone(): ?string
    {
        return $this->billing_phone;
    }

    public function setBillingPhone(?string $billing_phone): void
    {
        $this->billing_phone = $billing_phone;
    }

    public function getSalesRep(): ?string
    {
        return $this->sales_rep;
    }

    public function setSalesRep(string $sales_rep): void
    {
        $this->sales_rep = $sales_rep;
    }

    public function getDateFulfilled(): ?CarbonImmutable
    {
        if (!$this->date_fulfilled) {
            return null;
        }

        if (!($this->date_fulfilled instanceof CarbonImmutable)) {
            $this->date_fulfilled = new CarbonImmutable($this->date_fulfilled);
        }

        return $this->date_fulfilled;
    }

    public function setDateFulfilled(?CarbonImmutable $date_fulfilled): void
    {
        $this->date_fulfilled = $date_fulfilled;
    }

    public function getFulfilledBy(): ?string
    {
        return $this->fulfilled_by;
    }

    public function setFulfilledBy(?string $fulfilled_by): void
    {
        $this->fulfilled_by = $fulfilled_by;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAttachment(): ?string
    {
        return $this->attachment;
    }

    public function setAttachment(?string $attachment): void
    {
        $this->attachment = $attachment;
    }

    public function getAttachmentFile(): ?File
    {
        return $this->attachment_file;
    }

    public function setAttachmentFile(?File $attachmentFile): void
    {
        $this->attachment_file = $attachmentFile;
    }

    public function getAttachmentName(): ?string
    {
        return $this->attachment_name;
    }

    public function setAttachmentName(?string $attachment_name): void
    {
        $this->attachment_name = $attachment_name;
    }

    public function getNewAccount(): ?NewAccount
    {
        return $this->newAccount;
    }

    public function setNewAccount(?NewAccount $newAccount): void
    {
        $this->newAccount = $newAccount;
    }

    public function getCreatedTenant(): ?int
    {
        return $this->createdTenant;
    }

    public function setCreatedTenant(?int $createdTenant): void
    {
        $this->createdTenant = $createdTenant;
    }

    public function markFulfilledBySystem(): void
    {
        $this->date_fulfilled = CarbonImmutable::now();
        $this->status = 'complete';
        $this->fulfilled_by = 'System';
    }

    public function getSowAmount(): ?string
    {
        return $this->sow_amount;
    }

    public function setSowAmount(?string $sow_amount): void
    {
        $this->sow_amount = $sow_amount;
    }

    public function getRecurringAmount(): ?string
    {
        return $this->recurring_amount;
    }

    public function setRecurringAmount(?string $recurring_amount): void
    {
        $this->recurring_amount = $recurring_amount;
    }

    public function getRecurringInterval(): ?string
    {
        return $this->recurring_interval;
    }

    public function setRecurringInterval(?string $recurring_interval): void
    {
        $this->recurring_interval = $recurring_interval;
    }

    public function isBillingChange(): bool
    {
        return $this->billing_change;
    }

    public function setBillingChange(bool $billingChange): void
    {
        $this->billing_change = $billingChange;
    }

    public function isEntitlementChange(): bool
    {
        return $this->entitlement_change;
    }

    public function setEntitlementChange(bool $entitlementChange): void
    {
        $this->entitlement_change = $entitlementChange;
    }

    public function getChangeOrderType(): ?string
    {
        return $this->change_order_type;
    }

    public function setChangeOrderType(string $changeOrderType): void
    {
        $this->change_order_type = $changeOrderType;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): void
    {
        $this->contract = $contract;
    }

    public function getBillingProfileId(): int
    {
        return $this->billingProfileId;
    }

    public function setBillingProfileId(int $billingProfileId): void
    {
        $this->billingProfileId = $billingProfileId;
    }

    public function getProductPricingPlans(): mixed
    {
        return $this->productPricingPlans;
    }

    public function setProductPricingPlans(mixed $productPricingPlans): void
    {
        $this->productPricingPlans = $productPricingPlans;
    }

    public function getUsagePricingPlans(): mixed
    {
        return $this->usagePricingPlans;
    }

    public function setUsagePricingPlans(mixed $usagePricingPlans): void
    {
        $this->usagePricingPlans = $usagePricingPlans;
    }

    public function getNewUserCount(): ?int
    {
        return $this->newUserCount;
    }

    public function setNewUserCount(?int $newUserCount): void
    {
        $this->newUserCount = $newUserCount;
    }

    public function getBillingInterval(): ?int
    {
        return $this->billingInterval;
    }

    public function setBillingInterval(?int $billingInterval): void
    {
        $this->billingInterval = $billingInterval;
    }

    public function getBillingIntervalName(): string
    {
        return array_search($this->billingInterval, BillingProfileCrudController::BILLING_INTERVALS) ?: 'Unknown';
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getChangeset(): ?string
    {
        return $this->changeset;
    }

    public function setChangeset(?string $changeset): void
    {
        $this->changeset = $changeset;
    }
}
