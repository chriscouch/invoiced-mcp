<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'XeroSyncProfiles')]
class XeroSyncProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'integer')]
    private int $integration_version;
    #[ORM\Column(nullable: true)]
    private ?int $last_synced;
    #[ORM\Column(nullable: true)]
    private ?int $invoice_start_date;
    #[ORM\Column(nullable: true)]
    private ?int $read_cursor;
    #[ORM\Column(type: 'string')]
    private string $tax_mode;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $sales_tax_account;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $tax_type;
    #[ORM\Column(type: 'boolean')]
    private bool $add_tax_line_item;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $undeposited_funds_account;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $convenience_fee_account;
    #[ORM\Column(type: 'text')]
    private string $payment_accounts;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $item_account;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $discount_account;
    #[ORM\Column(type: 'boolean')]
    private bool $send_item_code;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $read_pdfs;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices_as_drafts;
    #[ORM\Column(type: 'boolean')]
    private bool $read_payments;
    #[ORM\Column(type: 'boolean')]
    private bool $read_credit_notes;
    #[ORM\Column(type: 'boolean')]
    private bool $read_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $write_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $write_credit_notes;
    #[ORM\Column(type: 'boolean')]
    private bool $write_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $write_payments;
    #[ORM\Column(type: 'boolean')]
    private bool $write_convenience_fees;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\OneToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function __toString(): string
    {
        return $this->tenant->getName().' Xero Integration';
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

    public function getIntegrationVersion(): int
    {
        return $this->integration_version;
    }

    public function setIntegrationVersion(int $integration_version): void
    {
        $this->integration_version = $integration_version;
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
            $this->last_synced = (int) $last_synced_datetime->format('U');
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
            $this->invoice_start_date = (int) $invoice_start_date_datetime->format('U');
        } else {
            $this->invoice_start_date = null;
        }
    }

    public function getSalesTaxAccount(): ?string
    {
        return $this->sales_tax_account;
    }

    public function setSalesTaxAccount(?string $sales_tax_account): void
    {
        $this->sales_tax_account = $sales_tax_account;
    }

    public function getTaxType(): ?string
    {
        return $this->tax_type;
    }

    public function setTaxType(?string $tax_type): void
    {
        $this->tax_type = $tax_type;
    }

    public function isAddTaxLineItem(): bool
    {
        return $this->add_tax_line_item;
    }

    public function setAddTaxLineItem(bool $add_tax_line_item): void
    {
        $this->add_tax_line_item = $add_tax_line_item;
    }

    public function getUndepositedFundsAccount(): ?string
    {
        return $this->undeposited_funds_account;
    }

    public function setUndepositedFundsAccount(?string $undeposited_funds_account): void
    {
        $this->undeposited_funds_account = $undeposited_funds_account;
    }

    public function getItemAccount(): ?string
    {
        return $this->item_account;
    }

    public function setItemAccount(?string $item_account): void
    {
        $this->item_account = $item_account;
    }

    public function isSendItemCode(): bool
    {
        return $this->send_item_code;
    }

    public function setSendItemCode(bool $send_item_code): void
    {
        $this->send_item_code = $send_item_code;
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

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getReadCursor(): ?DateTimeInterface
    {
        $read_cursor = $this->read_cursor;
        if (!isset($read_cursor)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($read_cursor);
    }

    public function setReadCursor(?DateTimeInterface $read_cursor): void
    {
        if (isset($read_cursor)) {
            $this->read_cursor = (int) $read_cursor->format('U');
        } else {
            $this->read_cursor = null;
        }
    }

    public function getTaxMode(): string
    {
        return $this->tax_mode;
    }

    public function setTaxMode(string $tax_mode): void
    {
        $this->tax_mode = $tax_mode;
    }

    public function getConvenienceFeeAccount(): ?string
    {
        return $this->convenience_fee_account;
    }

    public function setConvenienceFeeAccount(?string $convenience_fee_account): void
    {
        $this->convenience_fee_account = $convenience_fee_account;
    }

    public function getPaymentAccounts(): string
    {
        return $this->payment_accounts;
    }

    public function setPaymentAccounts(string $payment_accounts): void
    {
        $this->payment_accounts = $payment_accounts;
    }

    public function isReadCreditNotes(): bool
    {
        return $this->read_credit_notes;
    }

    public function setReadCreditNotes(bool $read_credit_notes): void
    {
        $this->read_credit_notes = $read_credit_notes;
    }

    public function isReadCustomers(): bool
    {
        return $this->read_customers;
    }

    public function setReadCustomers(bool $read_customers): void
    {
        $this->read_customers = $read_customers;
    }

    public function isWriteCustomers(): bool
    {
        return $this->write_customers;
    }

    public function setWriteCustomers(bool $write_customers): void
    {
        $this->write_customers = $write_customers;
    }

    public function isWriteCreditNotes(): bool
    {
        return $this->write_credit_notes;
    }

    public function setWriteCreditNotes(bool $write_credit_notes): void
    {
        $this->write_credit_notes = $write_credit_notes;
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

    public function isWriteConvenienceFees(): bool
    {
        return $this->write_convenience_fees;
    }

    public function setWriteConvenienceFees(bool $write_convenience_fees): void
    {
        $this->write_convenience_fees = $write_convenience_fees;
    }

    public function getDiscountAccount(): ?string
    {
        return $this->discount_account;
    }

    public function setDiscountAccount(?string $discount_account): void
    {
        $this->discount_account = $discount_account;
    }
}
