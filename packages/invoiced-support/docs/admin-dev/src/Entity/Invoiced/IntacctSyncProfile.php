<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'IntacctSyncProfiles')]
class IntacctSyncProfile
{
    #[ORM\OneToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\Column(type: 'integer')]
    private int $integration_version;
    #[ORM\Column(type: 'boolean')]
    private bool $read_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $write_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $read_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $write_invoices;
    #[ORM\Column(type: 'boolean')]
    private bool $write_to_order_entry;
    #[ORM\Column(type: 'boolean')]
    private bool $read_ar_adjustments;
    #[ORM\Column(type: 'boolean')]
    private bool $read_credit_notes;
    #[ORM\Column(type: 'boolean')]
    private bool $write_credit_notes;
    #[ORM\Column(type: 'boolean')]
    private bool $read_payments;
    #[ORM\Column(type: 'boolean')]
    private bool $write_payments;
    #[ORM\Column(nullable: true)]
    private ?int $last_synced;
    #[ORM\Column(nullable: true)]
    private ?int $read_cursor;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $item_account;
    #[ORM\Column(type: 'text')]
    private string $payment_accounts;
    #[ORM\Column(nullable: true)]
    private ?int $invoice_start_date;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $item_location_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $item_department_id;
    #[ORM\Column(type: 'text')]
    private string $invoice_types;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $invoice_import_mapping;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customer_read_query_addon;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ar_adjustment_read_query_addon;
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $invoice_import_query_addon;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $line_item_import_mapping;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customer_custom_field_mapping;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $invoice_custom_field_mapping;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $line_item_custom_field_mapping;
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'string', length: 255)]
    private string $customer_import_type;
    #[ORM\Column(type: 'boolean')]
    private bool $customer_top_level;
    #[ORM\Column(type: 'boolean')]
    private bool $map_catalog_item_to_item_id;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $invoice_location_id_filter;
    #[ORM\Column(type: 'boolean')]
    private bool $ship_to_invoice_distribution_list;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $payment_plan_import_settings;
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $read_batch_size;

    public function __toString(): string
    {
        return $this->tenant->getName().' Intacct Integration';
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
        if ($last_synced_datetime) {
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

    public function getIntegrationVersion(): int
    {
        return $this->integration_version;
    }

    public function setIntegrationVersion(int $integration_version): void
    {
        $this->integration_version = $integration_version;
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

    public function isReadInvoices(): bool
    {
        return $this->read_invoices;
    }

    public function setReadInvoices(bool $read_invoices): void
    {
        $this->read_invoices = $read_invoices;
    }

    public function isWriteInvoices(): bool
    {
        return $this->write_invoices;
    }

    public function setWriteInvoices(bool $write_invoices): void
    {
        $this->write_invoices = $write_invoices;
    }

    public function isWriteToOrderEntry(): bool
    {
        return $this->write_to_order_entry;
    }

    public function setWriteToOrderEntry(bool $write_to_order_entry): void
    {
        $this->write_to_order_entry = $write_to_order_entry;
    }

    public function isReadArAdjustments(): bool
    {
        return $this->read_ar_adjustments;
    }

    public function setReadArAdjustments(bool $read_ar_adjustments): void
    {
        $this->read_ar_adjustments = $read_ar_adjustments;
    }

    public function isReadCreditNotes(): bool
    {
        return $this->read_credit_notes;
    }

    public function setReadCreditNotes(bool $read_credit_notes): void
    {
        $this->read_credit_notes = $read_credit_notes;
    }

    public function isWriteCreditNotes(): bool
    {
        return $this->write_credit_notes;
    }

    public function setWriteCreditNotes(bool $write_credit_notes): void
    {
        $this->write_credit_notes = $write_credit_notes;
    }

    public function isReadPayments(): bool
    {
        return $this->read_payments;
    }

    public function setReadPayments(bool $read_payments): void
    {
        $this->read_payments = $read_payments;
    }

    public function isWritePayments(): bool
    {
        return $this->write_payments;
    }

    public function setWritePayments(bool $write_payments): void
    {
        $this->write_payments = $write_payments;
    }

    public function getItemAccount(): ?string
    {
        return $this->item_account;
    }

    public function setItemAccount(?string $item_account): void
    {
        $this->item_account = $item_account;
    }

    public function getPaymentAccounts(): string
    {
        return $this->payment_accounts;
    }

    public function setPaymentAccounts(string $payment_accounts): void
    {
        $this->payment_accounts = $payment_accounts;
    }

    public function getItemLocationId(): ?string
    {
        return $this->item_location_id;
    }

    public function setItemLocationId(?string $item_location_id): void
    {
        $this->item_location_id = $item_location_id;
    }

    public function getItemDepartmentId(): ?string
    {
        return $this->item_department_id;
    }

    public function setItemDepartmentId(?string $item_department_id): void
    {
        $this->item_department_id = $item_department_id;
    }

    public function getInvoiceTypes(): string
    {
        return $this->invoice_types;
    }

    public function setInvoiceTypes(string $invoice_types): void
    {
        $this->invoice_types = $invoice_types;
    }

    public function getInvoiceImportMapping(): ?string
    {
        return $this->invoice_import_mapping;
    }

    public function setInvoiceImportMapping(?string $invoice_import_mapping): void
    {
        $this->invoice_import_mapping = $invoice_import_mapping;
    }

    public function getInvoiceImportQueryAddon(): ?string
    {
        return $this->invoice_import_query_addon;
    }

    public function setInvoiceImportQueryAddon(?string $invoice_import_query_addon): void
    {
        $this->invoice_import_query_addon = $invoice_import_query_addon;
    }

    public function getLineItemImportMapping(): ?string
    {
        return $this->line_item_import_mapping;
    }

    public function setLineItemImportMapping(?string $line_item_import_mapping): void
    {
        $this->line_item_import_mapping = $line_item_import_mapping;
    }

    public function getCustomerCustomFieldMapping(): ?string
    {
        return $this->customer_custom_field_mapping;
    }

    public function setCustomerCustomFieldMapping(?string $customer_custom_field_mapping): void
    {
        $this->customer_custom_field_mapping = $customer_custom_field_mapping;
    }

    public function getInvoiceCustomFieldMapping(): ?string
    {
        return $this->invoice_custom_field_mapping;
    }

    public function setInvoiceCustomFieldMapping(?string $invoice_custom_field_mapping): void
    {
        $this->invoice_custom_field_mapping = $invoice_custom_field_mapping;
    }

    public function getLineItemCustomFieldMapping(): ?string
    {
        return $this->line_item_custom_field_mapping;
    }

    public function setLineItemCustomFieldMapping(?string $line_item_custom_field_mapping): void
    {
        $this->line_item_custom_field_mapping = $line_item_custom_field_mapping;
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

    public function getCustomerImportType(): string
    {
        return $this->customer_import_type;
    }

    public function setCustomerImportType(string $customer_import_type): void
    {
        $this->customer_import_type = $customer_import_type;
    }

    public function isMapCatalogItemToItemId(): bool
    {
        return $this->map_catalog_item_to_item_id;
    }

    public function setMapCatalogItemToItemId(bool $map_catalog_item_to_item_id): void
    {
        $this->map_catalog_item_to_item_id = $map_catalog_item_to_item_id;
    }

    public function getInvoiceLocationIdFilter(): ?string
    {
        return $this->invoice_location_id_filter;
    }

    public function setInvoiceLocationIdFilter(?string $invoice_location_id_filter): void
    {
        $this->invoice_location_id_filter = $invoice_location_id_filter;
    }

    public function isShipToInvoiceDistributionList(): bool
    {
        return $this->ship_to_invoice_distribution_list;
    }

    public function setShipToInvoiceDistributionList(bool $ship_to_invoice_distribution_list): void
    {
        $this->ship_to_invoice_distribution_list = $ship_to_invoice_distribution_list;
    }

    public function getPaymentPlanImportSettings(): ?string
    {
        return $this->payment_plan_import_settings;
    }

    public function setPaymentPlanImportSettings(?string $payment_plan_import_settings): void
    {
        $this->payment_plan_import_settings = $payment_plan_import_settings;
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

    public function getCustomerReadQueryAddon(): ?string
    {
        return $this->customer_read_query_addon;
    }

    public function setCustomerReadQueryAddon(?string $customer_read_query_addon): void
    {
        $this->customer_read_query_addon = $customer_read_query_addon;
    }

    public function getArAdjustmentReadQueryAddon(): ?string
    {
        return $this->ar_adjustment_read_query_addon;
    }

    public function setArAdjustmentReadQueryAddon(?string $ar_adjustment_read_query_addon): void
    {
        $this->ar_adjustment_read_query_addon = $ar_adjustment_read_query_addon;
    }

    public function isCustomerTopLevel(): bool
    {
        return $this->customer_top_level;
    }

    public function setCustomerTopLevel(bool $customer_top_level): void
    {
        $this->customer_top_level = $customer_top_level;
    }

    public function getReadBatchSize(): ?int
    {
        return $this->read_batch_size;
    }

    public function setReadBatchSize(?int $read_batch_size): void
    {
        $this->read_batch_size = $read_batch_size;
    }
}
