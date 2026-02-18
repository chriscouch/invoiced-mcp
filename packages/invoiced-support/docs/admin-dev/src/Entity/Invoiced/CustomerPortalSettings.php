<?php

namespace App\Entity\Invoiced;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'CustomerPortalSettings')]
class CustomerPortalSettings
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $tenant_id;
    #[ORM\OneToOne(targetEntity: '\App\Entity\Invoiced\Company')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_invoice_payment_selector;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_partial_payments;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_autopay_enrollment;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_billing_portal_cancellations;
    #[ORM\Column(type: 'boolean')]
    private bool $billing_portal_show_company_name;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_billing_portal_profile_changes;
    #[ORM\Column(type: 'string')]
    private string $google_analytics_id;
    private DateTimeInterface $created_at;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $updated_at;
    #[ORM\Column(type: 'boolean')]
    private bool $enabled;
    #[ORM\Column(type: 'boolean')]
    private bool $include_sub_customers;
    #[ORM\Column(type: 'boolean')]
    private bool $show_powered_by;
    #[ORM\Column(type: 'boolean')]
    private bool $require_authentication;
    #[ORM\Column(type: 'boolean')]
    private bool $allow_editing_contacts;
    #[ORM\Column(type: 'boolean')]
    private bool $invoice_payment_to_item_selection;
    #[ORM\Column(type: 'string')]
    private string $welcome_message;

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

    public function isAllowInvoicePaymentSelector(): bool
    {
        return $this->allow_invoice_payment_selector;
    }

    public function setAllowInvoicePaymentSelector(bool $allow_invoice_payment_selector): void
    {
        $this->allow_invoice_payment_selector = $allow_invoice_payment_selector;
    }

    public function isAllowPartialPayments(): bool
    {
        return $this->allow_partial_payments;
    }

    public function setAllowPartialPayments(bool $allow_partial_payments): void
    {
        $this->allow_partial_payments = $allow_partial_payments;
    }

    public function isAllowAutopayEnrollment(): bool
    {
        return $this->allow_autopay_enrollment;
    }

    public function setAllowAutopayEnrollment(bool $allow_autopay_enrollment): void
    {
        $this->allow_autopay_enrollment = $allow_autopay_enrollment;
    }

    public function isAllowBillingPortalCancellations(): bool
    {
        return $this->allow_billing_portal_cancellations;
    }

    public function setAllowBillingPortalCancellations(bool $allow_billing_portal_cancellations): void
    {
        $this->allow_billing_portal_cancellations = $allow_billing_portal_cancellations;
    }

    public function isBillingPortalShowCompanyName(): bool
    {
        return $this->billing_portal_show_company_name;
    }

    public function setBillingPortalShowCompanyName(bool $billing_portal_show_company_name): void
    {
        $this->billing_portal_show_company_name = $billing_portal_show_company_name;
    }

    public function isAllowBillingPortalProfileChanges(): bool
    {
        return $this->allow_billing_portal_profile_changes;
    }

    public function setAllowBillingPortalProfileChanges(bool $allow_billing_portal_profile_changes): void
    {
        $this->allow_billing_portal_profile_changes = $allow_billing_portal_profile_changes;
    }

    public function getGoogleAnalyticsId(): string
    {
        return $this->google_analytics_id;
    }

    public function setGoogleAnalyticsId(string $google_analytics_id): void
    {
        $this->google_analytics_id = $google_analytics_id;
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isIncludeSubCustomers(): bool
    {
        return $this->include_sub_customers;
    }

    public function setIncludeSubCustomers(bool $include_sub_customers): void
    {
        $this->include_sub_customers = $include_sub_customers;
    }

    public function isShowPoweredBy(): bool
    {
        return $this->show_powered_by;
    }

    public function setShowPoweredBy(bool $show_powered_by): void
    {
        $this->show_powered_by = $show_powered_by;
    }

    public function isRequireAuthentication(): bool
    {
        return $this->require_authentication;
    }

    public function setRequireAuthentication(bool $require_authentication): void
    {
        $this->require_authentication = $require_authentication;
    }

    public function isAllowEditingContacts(): bool
    {
        return $this->allow_editing_contacts;
    }

    public function setAllowEditingContacts(bool $allow_editing_contacts): void
    {
        $this->allow_editing_contacts = $allow_editing_contacts;
    }

    public function isInvoicePaymentToItemSelection(): bool
    {
        return $this->invoice_payment_to_item_selection;
    }

    public function setInvoicePaymentToItemSelection(bool $invoice_payment_to_item_selection): void
    {
        $this->invoice_payment_to_item_selection = $invoice_payment_to_item_selection;
    }

    public function getWelcomeMessage(): string
    {
        return $this->welcome_message;
    }

    public function setWelcomeMessage(string $welcome_message): void
    {
        $this->welcome_message = $welcome_message;
    }
}
