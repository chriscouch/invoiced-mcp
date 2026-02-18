<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'QuickBooksOnlineSyncProfiles')]
class QuickBooksOnlineSyncProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(nullable: true)]
    private ?string $last_synced;
    #[ORM\Column(nullable: true)]
    private ?string $invoice_start_date;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $discount_account;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $tax_code;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $undeposited_funds_account;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $read_pdfs;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices_as_drafts;
    #[ORM\Column(type: 'boolean')]
    private bool $read_payments;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $custom_field_1;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $custom_field_2;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $custom_field_3;
    #[ORM\Column(type: 'boolean')]
    private bool $namespace_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $namespace_items;
    #[ORM\Column(type: 'boolean')]
    private bool $namespace_invoices;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'boolean')]
    private bool $write_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $write_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $write_payments;
    #[ORM\Column(type: 'boolean')]
    private bool $write_credit_notes;
    #[ORM\Column(type: 'text')]
    private string $payment_accounts;
    #[ORM\Column(nullable: true)]
    private ?int $read_cursor;
    #[ORM\OneToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function __toString(): string
    {
        return $this->tenant->getName().' QuickBooks Online Integration';
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

    public function getLastSynced(): ?DateTimeInterface
    {
        $epoch = $this->last_synced;
        if (!isset($epoch)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($epoch);
    }

    public function setLastSynced(?DateTimeInterface $last_synced_datetime): void
    {
        if (isset($last_synced_datetime)) {
            $this->last_synced = $last_synced_datetime->format('U');
        } else {
            $this->last_synced = null;
        }
    }

    public function getInvoiceStartDate(): ?DateTimeInterface
    {
        $epoch = $this->invoice_start_date;
        if (!isset($epoch)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($epoch);
    }

    public function setInvoiceStartDate(?DateTimeInterface $invoice_start_date_datetime): void
    {
        if (isset($invoice_start_date_datetime)) {
            $this->invoice_start_date = $invoice_start_date_datetime->format('U');
        } else {
            $this->invoice_start_date = null;
        }
    }

    public function getReadCursor(): ?DateTimeInterface
    {
        $epoch = $this->read_cursor;
        if (!isset($epoch)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($epoch);
    }

    public function setReadCursor(?DateTimeInterface $read_cursor_datetime): void
    {
        if (isset($read_cursor_datetime)) {
            $this->read_cursor = (int) $read_cursor_datetime->format('U');
        } else {
            $this->read_cursor = null;
        }
    }

    public function getDiscountAccount(): ?string
    {
        return $this->discount_account;
    }

    public function setDiscountAccount(?string $discount_account): void
    {
        $this->discount_account = $discount_account;
    }

    public function getTaxCode(): ?string
    {
        return $this->tax_code;
    }

    public function setTaxCode(?string $tax_code): void
    {
        $this->tax_code = $tax_code;
    }

    public function getUndepositedFundsAccount(): ?string
    {
        return $this->undeposited_funds_account;
    }

    public function setUndepositedFundsAccount(?string $undeposited_funds_account): void
    {
        $this->undeposited_funds_account = $undeposited_funds_account;
    }

    public function isReadInvoices(): bool
    {
        return $this->read_invoices;
    }

    public function setReadInvoices(bool $read_invoices): void
    {
        $this->read_invoices = $read_invoices;
    }

    public function isReadPdfs(): bool
    {
        return $this->read_pdfs;
    }

    public function setReadPdfs(bool $read_pdfs): void
    {
        $this->read_pdfs = $read_pdfs;
    }

    public function isReadInvoicesAsDrafts(): bool
    {
        return $this->read_invoices_as_drafts;
    }

    public function setReadInvoicesAsDrafts(bool $read_invoices_as_drafts): void
    {
        $this->read_invoices_as_drafts = $read_invoices_as_drafts;
    }

    public function isReadPayments(): bool
    {
        return $this->read_payments;
    }

    public function setReadPayments(bool $read_payments): void
    {
        $this->read_payments = $read_payments;
    }

    public function getCustomField1(): ?string
    {
        return $this->custom_field_1;
    }

    public function setCustomField1(?string $custom_field_1): void
    {
        $this->custom_field_1 = $custom_field_1;
    }

    public function getCustomField2(): ?string
    {
        return $this->custom_field_2;
    }

    public function setCustomField2(?string $custom_field_2): void
    {
        $this->custom_field_2 = $custom_field_2;
    }

    public function getCustomField3(): ?string
    {
        return $this->custom_field_3;
    }

    public function setCustomField3(?string $custom_field_3): void
    {
        $this->custom_field_3 = $custom_field_3;
    }

    public function isNamespaceCustomers(): bool
    {
        return $this->namespace_customers;
    }

    public function setNamespaceCustomers(bool $namespace_customers): void
    {
        $this->namespace_customers = $namespace_customers;
    }

    public function isNamespaceItems(): bool
    {
        return $this->namespace_items;
    }

    public function setNamespaceItems(bool $namespace_items): void
    {
        $this->namespace_items = $namespace_items;
    }

    public function isNamespaceInvoices(): bool
    {
        return $this->namespace_invoices;
    }

    public function setNamespaceInvoices(bool $namespace_invoices): void
    {
        $this->namespace_invoices = $namespace_invoices;
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

    public function isWriteCustomers(): bool
    {
        return $this->write_customers;
    }

    public function setWriteCustomers(bool $write_customers): void
    {
        $this->write_customers = $write_customers;
    }

    public function isWriteInvoices(): bool
    {
        return $this->write_invoices;
    }

    public function setWriteInvoices(bool $write_invoices): void
    {
        $this->write_invoices = $write_invoices;
    }

    public function isWritePayments(): bool
    {
        return $this->write_payments;
    }

    public function setWritePayments(bool $write_payments): void
    {
        $this->write_payments = $write_payments;
    }

    public function isWriteCreditNotes(): bool
    {
        return $this->write_credit_notes;
    }

    public function setWriteCreditNotes(bool $write_credit_notes): void
    {
        $this->write_credit_notes = $write_credit_notes;
    }

    public function getPaymentAccounts(): string
    {
        return $this->payment_accounts;
    }

    public function setPaymentAccounts(string $payment_accounts): void
    {
        $this->payment_accounts = $payment_accounts;
    }

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
    }
}
