<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AccountsReceivableSettings')]
class AccountsReceivableSettings
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\Company')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Column(type: 'boolean')]
    private bool $chase_new_invoices;
    #[ORM\Column(type: 'string')]
    private string $default_collection_mode;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $payment_terms;
    #[ORM\Column(type: 'string')]
    private string $aging_buckets;
    #[ORM\Column(type: 'string')]
    private string $aging_date;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $default_template_id;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $default_theme_id;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $add_payment_plan_on_import;
    #[ORM\Column(type: 'boolean')]
    private bool $default_consolidated_invoicing;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $unit_cost_precision;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_chasing;
    #[ORM\Column(type: 'text')]
    private string $chase_schedule;
    #[ORM\Column(type: 'integer')]
    private int $autopay_delay_days;
    #[ORM\Column(type: 'string')]
    private string $payment_retry_schedule;
    #[ORM\Column(type: 'boolean')]
    private bool $transactions_inherit_invoice_metadata;
    #[ORM\Column(type: 'boolean')]
    private bool $auto_apply_credits;
    #[ORM\Column(type: 'boolean')]
    private bool $saved_cards_require_cvc;
    #[ORM\Column(type: 'boolean')]
    private bool $debit_cards_only;
    #[ORM\Column(type: 'boolean')]
    private string $email_provider;
    #[ORM\Column(type: 'string')]
    private string $bcc;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $reply_to_inbox_id;
    #[ORM\Column(type: 'string')]
    private string $tax_calculator;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;

    public function __toString(): string
    {
        return $this->tenant->getName().' Settings';
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

    public function isChaseNewInvoices(): bool
    {
        return $this->chase_new_invoices;
    }

    public function setChaseNewInvoices(bool $chase_new_invoices): void
    {
        $this->chase_new_invoices = $chase_new_invoices;
    }

    public function getDefaultCollectionMode(): string
    {
        return $this->default_collection_mode;
    }

    public function setDefaultCollectionMode(string $default_collection_mode): void
    {
        $this->default_collection_mode = $default_collection_mode;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->payment_terms;
    }

    public function setPaymentTerms(?string $payment_terms): void
    {
        $this->payment_terms = $payment_terms;
    }

    public function getAgingBuckets(): string
    {
        return $this->aging_buckets;
    }

    public function setAgingBuckets(string $aging_buckets): void
    {
        $this->aging_buckets = $aging_buckets;
    }

    public function getAgingDate(): string
    {
        return $this->aging_date;
    }

    public function setAgingDate(string $aging_date): void
    {
        $this->aging_date = $aging_date;
    }

    public function getDefaultTemplateId(): ?int
    {
        return $this->default_template_id;
    }

    public function setDefaultTemplateId(?int $default_template_id): void
    {
        $this->default_template_id = $default_template_id;
    }

    public function getDefaultThemeId(): ?int
    {
        return $this->default_theme_id;
    }

    public function setDefaultThemeId(?int $default_theme_id): void
    {
        $this->default_theme_id = $default_theme_id;
    }

    public function getAddPaymentPlanOnImport(): ?string
    {
        return $this->add_payment_plan_on_import;
    }

    public function setAddPaymentPlanOnImport(?string $add_payment_plan_on_import): void
    {
        $this->add_payment_plan_on_import = $add_payment_plan_on_import;
    }

    public function isDefaultConsolidatedInvoicing(): bool
    {
        return $this->default_consolidated_invoicing;
    }

    public function setDefaultConsolidatedInvoicing(bool $default_consolidated_invoicing): void
    {
        $this->default_consolidated_invoicing = $default_consolidated_invoicing;
    }

    public function getUnitCostPrecision(): ?int
    {
        return $this->unit_cost_precision;
    }

    public function setUnitCostPrecision(?int $unit_cost_precision): void
    {
        $this->unit_cost_precision = $unit_cost_precision;
    }

    public function isAllowChasing(): bool
    {
        return $this->allow_chasing;
    }

    public function setAllowChasing(bool $allow_chasing): void
    {
        $this->allow_chasing = $allow_chasing;
    }

    public function getChaseSchedule(): string
    {
        return $this->chase_schedule;
    }

    public function setChaseSchedule(string $chase_schedule): void
    {
        $this->chase_schedule = $chase_schedule;
    }

    public function getAutopayDelayDays(): int
    {
        return $this->autopay_delay_days;
    }

    public function setAutopayDelayDays(int $autopay_delay_days): void
    {
        $this->autopay_delay_days = $autopay_delay_days;
    }

    public function getPaymentRetrySchedule(): string
    {
        return $this->payment_retry_schedule;
    }

    public function setPaymentRetrySchedule(string $payment_retry_schedule): void
    {
        $this->payment_retry_schedule = $payment_retry_schedule;
    }

    public function isTransactionsInheritInvoiceMetadata(): bool
    {
        return $this->transactions_inherit_invoice_metadata;
    }

    public function setTransactionsInheritInvoiceMetadata(bool $transactions_inherit_invoice_metadata): void
    {
        $this->transactions_inherit_invoice_metadata = $transactions_inherit_invoice_metadata;
    }

    public function isAutoApplyCredits(): bool
    {
        return $this->auto_apply_credits;
    }

    public function setAutoApplyCredits(bool $auto_apply_credits): void
    {
        $this->auto_apply_credits = $auto_apply_credits;
    }

    public function isSavedCardsRequireCvc(): bool
    {
        return $this->saved_cards_require_cvc;
    }

    public function setSavedCardsRequireCvc(bool $saved_cards_require_cvc): void
    {
        $this->saved_cards_require_cvc = $saved_cards_require_cvc;
    }

    public function isDebitCardsOnly(): bool
    {
        return $this->debit_cards_only;
    }

    public function setDebitCardsOnly(bool $debit_cards_only): void
    {
        $this->debit_cards_only = $debit_cards_only;
    }

    public function getEmailProvider(): string
    {
        return $this->email_provider;
    }

    public function setEmailProvider(string $email_provider): void
    {
        $this->email_provider = $email_provider;
    }

    public function getBcc(): string
    {
        return $this->bcc;
    }

    public function setBcc(string $bcc): void
    {
        $this->bcc = $bcc;
    }

    public function getReplyToInboxId(): ?int
    {
        return $this->reply_to_inbox_id;
    }

    public function setReplyToInboxId(?int $reply_to_inbox_id): void
    {
        $this->reply_to_inbox_id = $reply_to_inbox_id;
    }

    public function getTaxCalculator(): string
    {
        return $this->tax_calculator;
    }

    public function setTaxCalculator(string $tax_calculator): void
    {
        $this->tax_calculator = $tax_calculator;
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
}
