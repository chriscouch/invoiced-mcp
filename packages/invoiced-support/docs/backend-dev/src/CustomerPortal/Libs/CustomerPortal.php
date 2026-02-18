<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\CustomerPortalAttachment;
use App\Core\I18n\PhoneFormatter;
use App\CustomerPortal\Models\CustomerPortalSettings;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;
use Twig\Environment;

final class CustomerPortal
{
    private const LANGUAGES = [
        ['code' => 'da', 'name' => 'Dansk'],
        ['code' => 'de', 'name' => 'Deutsch'],
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'es', 'name' => 'Español'],
        ['code' => 'fr', 'name' => 'Français'],
        ['code' => 'id', 'name' => 'Bahasa Indonesia'],
        ['code' => 'it', 'name' => 'Italiano'],
        ['code' => 'nl', 'name' => 'Nederlands'],
        ['code' => 'no', 'name' => 'Norsk'],
        ['code' => 'pl', 'name' => 'Polski'],
        ['code' => 'pt', 'name' => 'Português'],
        ['code' => 'ro', 'name' => 'Română'],
        ['code' => 'sv', 'name' => 'Svenska'],
        ['code' => 'th', 'name' => 'ภาษาไทย'],
        ['code' => 'tr', 'name' => 'Türkçe'],
        ['code' => 'uk', 'name' => 'Українська'],
    ];

    private ?Customer $customer = null;
    /** @var int[] */
    private array $customerIds;
    private ?string $email = null;
    private ?User $user = null;
    private string $locale;

    public function __construct(
        private readonly Company $company,
        private readonly CustomerHierarchy $hierarchy,
    ) {
        $this->company->useTimezone();
        $this->locale = $this->company->getLocale();
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Gets the company for this customer portal.
     */
    public function company(): Company
    {
        return $this->company;
    }

    /**
     * Checks if the customer portal is enabled for this company.
     */
    public function enabled(): bool
    {
        return $this->company->customer_portal_settings->enabled;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Generates a JWT-encoded login token for a customer.
     *
     * @param int $ttl seconds until the login token expires
     */
    public function generateLoginToken(Customer $customer, int $ttl): string
    {
        return (new JWTLoginLinkGenerator())->generateToken($this->company, $customer, $ttl);
    }

    /**
     * Gets a customer for a JWT-encoded login token.
     */
    public function getCustomerFromToken(?string $token): ?Customer
    {
        if (!$token) {
            return null;
        }

        try {
            $decrypted = (array) JWT::decode($token, new Key($this->company->sso_key, JWTLoginLinkGenerator::JWT_ALGORITHM));

            return Customer::queryWithTenant($this->company)
                ->where('id', $decrypted['sub'])
                ->oneOrNull();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Setting to tell if company name should be shown on customer portal.
     */
    public function showCompanyName(): bool
    {
        return $this->company->customer_portal_settings->billing_portal_show_company_name;
    }

    public function getPaymentFormSettings(): PaymentFormSettings
    {
        $settings = $this->company->customer_portal_settings;

        return new PaymentFormSettings(
            company: $this->company,
            allowPartialPayments: $settings->allow_partial_payments,
            allowApplyingCredits: $settings->allow_invoice_payment_selector,
            allowAdvancePayments: $settings->allow_advance_payments,
            allowAutoPayEnrollment: $settings->allow_autopay_enrollment,
        );
    }

    public function invoicePaymentToItemSelection(): bool
    {
        return $this->company->customer_portal_settings->invoice_payment_to_item_selection;
    }

    /**
     * Indicates whether customers can cancel their active subscriptions.
     */
    public function allowSubscriptionCancellation(): bool
    {
        return $this->company->customer_portal_settings->allow_billing_portal_cancellations;
    }

    /**
     * Indicates whether customers can edit their contact information.
     */
    public function allowEditingContactInformation(): bool
    {
        return $this->company->customer_portal_settings->allow_billing_portal_profile_changes;
    }

    /**
     * Indicates whether estimates are enabled for this portal.
     */
    public function hasEstimates(): bool
    {
        return $this->company->features->has('estimates');
    }

    /**
     * Indicates whether customers must be signed in to view any private page in the customer portal.
     */
    public function requiresAuthentication(): bool
    {
        return $this->company->customer_portal_settings->require_authentication;
    }

    /**
     * Gets the custom authentication URL for this portal.
     */
    public function getCustomAuthUrl(): ?string
    {
        return $this->company->customer_portal_settings->customer_portal_auth_url;
    }

    /**
     * Gets the currently signed in customer, if present.
     */
    public function getSignedInCustomer(): ?Customer
    {
        return $this->customer;
    }

    /**
     * Sets the currently signed in customer.
     */
    public function setSignedInCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
        unset($this->customerIds);
    }

    /**
     * Gets the currently signed in email address, if present.
     */
    public function getSignedInEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Sets the currently signed in email address.
     */
    public function setSignedInEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * Gets the currently signed in user, if present.
     */
    public function getSignedInUser(): ?User
    {
        return $this->user;
    }

    /**
     * Sets the currently signed in user.
     */
    public function setSignedInUser(?User $user): void
    {
        $this->user = $user;
    }

    /**
     * Sets the Twig global variables. This should be done at the
     * beginning of a customer portal request after the current
     * customer is determined AND after any time a customer is signed in.
     */
    public function setTwigGlobals(Environment $twig): void
    {
        $attachmentsCount = 0;
        if ($this->customer && $this->enabled()) {
            $attachmentsCount = CustomerPortalAttachment::query()->count();
            $attachmentsCount += Attachment::countForObject($this->customer, Attachment::LOCATION_ATTACHMENT);
        }
        $company = $this->company->toArray();
        $company['display_name'] = $this->company->getDisplayName();
        $company['address'] = $this->company->address(true, false);
        $phoneFormat = $this->customer?->country != $this->company->country ? PhoneNumberFormat::INTERNATIONAL : PhoneNumberFormat::NATIONAL;
        $company['phone'] = PhoneFormatter::format((string) $this->company->phone, $this->company->country, $phoneFormat);
        $twig->addGlobal('company', $company);
        $twig->addGlobal('settings', $this->company->customer_portal_settings);
        $twig->addGlobal('attachmentCount', $attachmentsCount);
        $twig->addGlobal('showPoweredBy', $this->company->customer_portal_settings->show_powered_by);
        $twig->addGlobal('googleAnalyticsId', $this->company->customer_portal_settings->google_analytics_id);
        $twig->addGlobal('customerPortal', $this);
        $twig->addGlobal('signedInCustomer', $this->customer);
        $twig->addGlobal('subdomain', $this->company->getSubdomainUsername());

        if ($this->customer) {
            $twig->addGlobal('pageData', (object) [
                'customer' => [
                    'id' => $this->customer->id(),
                ],
            ]);
        }

        // Language
        $locale = $this->getLocale();
        $currentLanguage = null;
        foreach (self::LANGUAGES as $language) {
            if (str_starts_with($locale, $language['code'])) {
                $currentLanguage = $language;
                break;
            }
        }
        if (!$currentLanguage) {
            $currentLanguage = ['name' => 'English', 'code' => 'en'];
        }
        $twig->addGlobal('currentLanguage', $currentLanguage);
        $twig->addGlobal('languages', self::LANGUAGES);

        // Money formatting
        $moneyOptions = $this->customer?->moneyFormat() ?? $this->company->moneyFormat();
        $moneyOptions['locale'] = $locale;
        $unitCostMoneyOptions = $moneyOptions;
        $precision = $this->company->accounts_receivable_settings->unit_cost_precision;
        if (null !== $precision) {
            $unitCostMoneyOptions['precision'] = $precision;
        }
        $twig->addGlobal('_moneyOptions', $moneyOptions);
        $twig->addGlobal('_unitCostMoneyOptions', $unitCostMoneyOptions);
    }

    /**
     * @return int[] customer ids that are allowed to view this portal
     */
    public function getAllowCustomerIds(): array
    {
        if (!$this->customer) {
            throw new UnauthorizedHttpException('');
        }

        if (isset($this->customerIds)) {
            return $this->customerIds;
        }
        $settings = CustomerPortalSettings::query()->oneOrNull();
        $this->customerIds = $settings?->include_sub_customers && $this->customer->id ? $this->hierarchy->getSubCustomerIds($this->customer) : [];
        $this->customerIds[] = $this->customer->id;

        return $this->customerIds;
    }

    public function match(int $customer): bool
    {
        try {
            return in_array($customer, $this->getAllowCustomerIds());
        } catch (UnauthorizedHttpException) {
            return false;
        }
    }
}
