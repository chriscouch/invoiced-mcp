<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'AccountingSyncProfiles')]
class AccountingSyncProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\OneToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'integer')]
    private int $integration;
    #[ORM\Column(nullable: true)]
    private ?string $last_synced;
    #[ORM\Column(nullable: true)]
    private ?string $invoice_start_date;
    #[ORM\Column(type: 'boolean')]
    private bool $read_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $read_pdfs;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices_as_drafts;
    #[ORM\Column(type: 'boolean')]
    private bool $read_credit_notes;
    #[ORM\Column(type: 'boolean')]
    private bool $read_payments;
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
    #[ORM\Column(type: 'text')]
    private string $parameters;
    #[ORM\Column(nullable: true)]
    private ?int $read_cursor;

    public function __toString(): string
    {
        return $this->tenant->getName().' Accounting Sync Profile # '.$this->id;
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

    public function isReadCreditNotes(): bool
    {
        return $this->read_credit_notes;
    }

    public function setReadCreditNotes(bool $read_credit_notes): void
    {
        $this->read_credit_notes = $read_credit_notes;
    }

    public function getIntegration(): int
    {
        return $this->integration;
    }

    public function setIntegration(int $integration): void
    {
        $this->integration = $integration;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function isReadCustomers(): bool
    {
        return $this->read_customers;
    }

    public function setReadCustomers(bool $read_customers): void
    {
        $this->read_customers = $read_customers;
    }

    public function getParameters(): string
    {
        return $this->parameters;
    }

    public function setParameters(string $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getTenantId(): int
    {
        return $this->tenant_id;
    }

    public function setTenantId(int $tenant_id): void
    {
        $this->tenant_id = $tenant_id;
    }
}
