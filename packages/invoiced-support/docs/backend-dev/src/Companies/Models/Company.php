<?php

namespace App\Companies\Models;

use App\AccountsPayable\Models\AccountsPayableSettings;
use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\CashApplicationSettings;
use App\CashApplication\Models\Payment;
use App\Companies\EmailVariables\CompanyEmailVariables;
use App\Companies\Libs\LogoUploader;
use App\Companies\Pdf\CompanyPdfVariables;
use App\Companies\ValueObjects\HighlightColor;
use App\Companies\Verification\EmailVerification;
use App\Core\Authentication\Libs\UserContextFacade;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\ValueObjects\BillingSubscriptionStatusGenerator;
use App\Core\CacheFacade;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Entitlements\FeatureCollection;
use App\Core\Entitlements\QuotaCollection;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Countries;
use App\Core\I18n\Currencies;
use App\Core\LockFactoryFacade;
use App\Core\LoggerFacade;
use App\Core\Mailer\MailerFacade;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Models\ApiKey;
use App\Core\Utils\AppUrl;
use App\Core\Utils\RandomString;
use App\CustomerPortal\Models\CustomerPortalSettings;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\IsEmailParticipantInterface;
use App\Sending\Email\Traits\IsEmailParticipantTrait;
use App\SubscriptionBilling\Models\SubscriptionBillingSettings;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Models\Theme;
use App\Tokenization\Models\PublishableKey;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * @property int                         $id
 * @property string                      $name
 * @property string|null                 $nickname
 * @property string|null                 $email
 * @property string                      $username
 * @property string                      $identifier
 * @property string|null                 $custom_domain
 * @property string|null                 $type
 * @property string|null                 $industry
 * @property string|null                 $address1
 * @property string|null                 $address2
 * @property string|null                 $city
 * @property string|null                 $state
 * @property string|null                 $postal_code
 * @property string|null                 $country
 * @property string|null                 $tax_id
 * @property string                      $address_extra
 * @property string|null                 $website
 * @property string|null                 $phone
 * @property int|null                    $creator_id
 * @property bool                        $test_mode
 * @property bool                        $fraud
 * @property string                      $logo
 * @property string                      $highlight_color
 * @property string                      $currency
 * @property bool                        $show_currency_code
 * @property string                      $date_format
 * @property string                      $time_zone
 * @property string                      $language
 * @property string                      $sso_key
 * @property string                      $sso_key_enc
 * @property int|null                    $search_last_reindexed
 * @property int|null                    $last_overage_notification
 * @property AccountsReceivableSettings  $accounts_receivable_settings
 * @property AccountsPayableSettings     $accounts_payable_settings
 * @property CashApplicationSettings     $cash_application_settings
 * @property CustomerPortalSettings      $customer_portal_settings
 * @property SubscriptionBillingSettings $subscription_billing_settings
 * @property string                      $url
 * @property array                       $billing
 * @property FeatureCollection           $features
 * @property QuotaCollection             $quota
 * @property BillingProfile|null         $billing_profile
 * @property int|null                    $billing_profile_id
 * @property array                       $currencies
 * @property int|null                    $trial_ends
 * @property bool                        $canceled
 * @property int|null                    $canceled_at
 * @property int|null                    $last_trial_reminder
 * @property int|null                    $trial_started
 * @property int|null                    $converted_at
 * @property string                      $converted_from
 * @property string                      $canceled_reason
 */
class Company extends Model implements ThemeableInterface, IsEmailParticipantInterface
{
    use AutoTimestamps;
    use IsEmailParticipantTrait;

    private const PROTECTED_USERNAMES = [
        'help',
        'pricing',
        'about',
        'contact',
        'support',
        'blog',
        'developer',
        'email',
        'email2',
        'click',
        'invoice',
        'invoicegenerator',
        'mail',
        'news',
        'video',
        'book',
        'books',
        'guide',
        'docs',
        'developers',
        'checkout',
        'payment',
        'payments',
        'finance',
        'signup',
        'join',
        'register',
        'login',
        'admin',
        'administrator',
        'trial',
        'dashboard',
        'logout',
        'signout',
        'account',
        'forgot',
        'verify',
        'setup',
        'company',
        'stripe',
        'companies',
        'api',
        'templates',
        'img',
        'image',
        'images',
        'css',
        'js',
        'javascript',
        'free',
        'statistics',
        'users',
        'user',
        'groups',
        'report',
        'reports',
        'null',
        'migrations',
        'secure',
        'logo',
        'logos',
        'manage',
        'invoicedhq',
        'widget',
        'invoiced',
        'status',
        'robot',
    ];

    private array $_moneyFormat;
    private array $_protectedApiKeys = [];
    private ?AccountsReceivableSettings $arSettings = null;
    private ?AccountsPayableSettings $apSettings = null;
    private ?CashApplicationSettings $cashAppSettings = null;
    private ?CustomerPortalSettings $customerPortalSettings = null;
    private ?SubscriptionBillingSettings $subscriptionBillingSettings = null;
    private ?Theme $_defaultTheme = null;
    private bool $_requestEmailVerification = false;
    private FeatureCollection $_features;
    private QuotaCollection $_quota;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(),
            'nickname' => new Property(
                null: true,
            ),
            'email' => new Property(
                null: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateEmail']],
                    ['email', 'column' => 'email'],
                ],
            ),
            'industry' => new Property(
                null: true,
            ),
            'username' => new Property(
                required: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateUsername']],
                    ['unique', 'column' => 'username'],
                ],
            ),
            'identifier' => new Property(
                required: true,
                in_array: false,
            ),
            'custom_domain' => new Property(
                null: true,
                in_array: false,
            ),
            'type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['company', 'government', 'non_profit', 'person']],
            ),
            'address1' => new Property(
                null: true,
            ),
            'address2' => new Property(
                null: true,
            ),
            'city' => new Property(
                null: true,
            ),
            'state' => new Property(
                null: true,
            ),
            'postal_code' => new Property(
                null: true,
            ),
            'country' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Countries::class, 'validateCountry']],
            ),
            'tax_id' => new Property(
                null: true,
            ),
            'address_extra' => new Property(
                null: true,
            ),
            'phone' => new Property(
                null: true,
                validate: ['string', 'max' => 25],
            ),
            'website' => new Property(
                null: true,
                validate: [
                    ['string', 'max' => 255],
                    ['url'],
                ],
            ),

            /* Billing */

            'billing_profile' => new Property(
                null: true,
                in_array: false,
                belongs_to: BillingProfile::class,
            ),
            'billing_profile_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'trial_ends' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'canceled' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'canceled_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'last_trial_reminder' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),

            /* Conversion Tracking */

            'trial_started' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'converted_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'converted_from' => new Property(
                in_array: false,
            ),
            'canceled_reason' => new Property(
                in_array: false,
            ),

            /* Setup process */

            'creator_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: User::class,
            ),
            'test_mode' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'fraud' => new Property(
                type: Type::BOOLEAN,
                in_array: false,
            ),

            /* Branding */

            'logo' => new Property(),
            'highlight_color' => new Property(
                validate: ['callable', 'fn' => [self::class, 'validateHighlightColor']],
                default: '#303030',
            ),

            /* Localization */

            'currency' => new Property(
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'show_currency_code' => new Property(
                type: Type::BOOLEAN,
            ),
            'date_format' => new Property(
                default: 'M j, Y',
            ),
            'time_zone' => new Property(
                default: ''
            ),
            'language' => new Property(
                required: true,
                validate: ['string', 'min' => 2, 'max' => 2],
                default: 'en',
            ),

            /* Customer Portal */

            'sso_key_enc' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                encrypted: true,
                in_array: false,
            ),

            /* Search */

            'search_last_reindexed' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),

            /* Email updates */

            'last_overage_notification' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
        ];
    }

    //
    // Hooks
    //

    protected function initialize(): void
    {
        self::creating([self::class, 'checkIfCompletedSetup']);
        self::creating([self::class, 'genSSOKey']);
        self::creating([self::class, 'setCurrency']);
        self::creating([self::class, 'genIdentifier']);
        self::created([self::class, 'genPublishableKey']);
        self::saving([self::class, 'validateTimeZone']);
        self::created([self::class, 'createRoles']);
        // should be always prior addCreatorAsMember
        self::created([self::class, 'createCompanyNotificationsSettings']);
        self::created([self::class, 'addCreatorAsMember']);
        self::updating([self::class, 'beforeUpdate'], -513);
        self::saved([self::class, 'sendVerificationEmail']);
        self::afterPersist(function (): void {
            FeatureCollection::clearCache();
        });

        parent::initialize();
    }

    public static function checkIfCompletedSetup(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->email) {
            $model->_requestEmailVerification = true;
        }
    }

    public static function genSSOKey(AbstractEvent $event): void
    {
        // generate a random key for SSO
        $event->getModel()->sso_key_enc = RandomString::generate(63, RandomString::CHAR_ALNUM);
    }

    public static function genPublishableKey(AbstractEvent $event): void
    {
        $key = new PublishableKey();
        $key->tenant_id = (int) $event->getModel()->id();
        $key->setSecret();
        $key->save();
    }

    public function getPublishableKey(): ?string
    {
        $publishableKey = PublishableKey::where('tenant_id', $this->id)
            ->oneOrNull();

        return $publishableKey?->secret;
    }

    /**
     * Sets the currency of the company based on the country.
     */
    public static function setCurrency(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->currency) {
            $countries = new Countries();
            $country = $countries->get((string) $model->country);
            if ($country) {
                $model->currency = $country['currency'];
            }
        }
    }

    /**
     * Add creator as member to company.
     */
    public static function addCreatorAsMember(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($creator = $model->creator()) {
            $member = new Member();
            $member->role = Role::ADMINISTRATOR;
            $member->tenant_id = (int) $model->id();
            $member->setUser($creator);

            if (!$member->skipMemberCheck()->save()) {
                throw new ListenerException('Could not add creator to company: '.$member->getErrors(), ['field' => 'creator']);
            }
        }
    }

    public static function genIdentifier(AbstractEvent $event): void
    {
        // generate a random string for use as an external identifier
        $event->getModel()->identifier = RandomString::generate(24, 'abcdefghijklmnopqrstuvwxyz1234567890');
    }

    public static function validateTimeZone(AbstractEvent $event): void
    {
        $timezone = $event->getModel()->timezone;
        if (!$timezone) {
            return;
        }

        try {
            $tz = new DateTimeZone($timezone);
        } catch (Exception) {
            throw new ListenerException('Please enter a valid time zone');
        }
    }

    public static function createRoles(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $role = new Role();
        $role->id = Role::ADMINISTRATOR;
        $role->tenant_id = (int) $model->id();
        $role->name = 'Administrator';

        foreach (Role::PERMISSIONS as $k => $name) {
            $role->$k = true;
        }

        if (!$role->save()) {
            throw new ListenerException('Could not create role: '.$role->getErrors());
        }
    }

    public static function createCompanyNotificationsSettings(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->features->enable('notifications_v2_default');

        foreach (NotificationEventType::cases() as $item) {
            if (NotificationEventType::ReconciliationError === $item) {
                continue;
            }
            $event = new NotificationEventCompanySetting();
            $event->tenant_id = (int) $model->id();
            $event->setNotificationType($item);
            $event->setFrequency(NotificationFrequency::Instant);
            $event->save();
        }
    }

    public static function beforeUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // send a verification email if the email address has changed
        if ($model->dirty('email', true)) {
            $model->_requestEmailVerification = true;
        }

        // verify currency change
        if ($model->dirty('currency', true)) {
            if (0 != Invoice::query()->count() ||
                0 != Estimate::query()->count() ||
                0 != CreditNote::query()->count() ||
                0 != Payment::query()->count()
            ) {
                throw new ListenerException('Cannot change currency because one or more transactions already exist.');
            }
        }
    }

    /**
     * Sends an email verification request when the email address changes.
     */
    public static function sendVerificationEmail(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->_requestEmailVerification) {
            $model->_requestEmailVerification = false;
            $verification = new EmailVerification(
                LockFactoryFacade::get(),
                MailerFacade::get()
            );
            $verification->start($model);
        }
    }

    //
    // Mutators
    //

    /**
     * Sets the country property.
     *
     * @param string $country
     */
    protected function setCountryValue($country): string
    {
        return strtoupper($country);
    }

    /**
     * Sets the currency property.
     *
     * @param string $currency
     */
    protected function setCurrencyValue($currency): string
    {
        return strtolower($currency);
    }

    /**
     * Sets the language property.
     *
     * @param ?string $language
     */
    protected function setLanguageValue($language): ?string
    {
        if (!$language) {
            return null;
        }

        return strtolower($language);
    }

    /**
     * Sets the logo property.
     *
     * @param string $logo
     */
    protected function setLogoValue($logo): string
    {
        $prefix = LogoUploader::S3_PUBLIC_ENDPOINT;

        return str_replace($prefix, '', $logo);
    }

    //
    // Accessors
    //

    /**
     * Gets the URL for the company's logo.
     */
    protected function getLogoValue(mixed $logo): ?string
    {
        if ($logo) {
            return LogoUploader::S3_PUBLIC_ENDPOINT.$logo;
        }

        return null;
    }

    /**
     * Gets the URL for the company's customer portal.
     */
    protected function getUrlValue(): ?string
    {
        if ($domain = $this->custom_domain) {
            return 'https://'.$domain;
        }

        if (!$this->username) {
            return null;
        }

        // inject username as subdomain of base url with no trailing "/"
        return AppUrl::get()->buildSubdomain($this->getSubdomainUsername());
    }

    /**
     * Gets the display name of the company which will
     * be the DBA name if there is one. Otherwise, uses
     * the company legal name.
     */
    public function getDisplayName(): string
    {
        return $this->nickname ?: $this->name;
    }

    public function getSubdomainUsername(): string
    {
        return strtolower($this->username);
    }

    public function getSubdomainHostname(): string
    {
        // inject username as subdomain of base url with no trailing "/"
        return AppUrl::get()->buildSubdomain($this->getSubdomainUsername(), false);
    }

    /**
     * Gets the decrypted `sso_key` property.
     *
     * @param mixed $key current value
     */
    protected function getSsoKeyValue($key): ?string
    {
        if ($key || !$this->sso_key_enc) {
            return $key;
        }

        return $this->sso_key_enc;
    }

    /**
     * Gets the `billing` property.
     */
    public function getBillingValue(): array
    {
        $billingProfile = BillingProfile::getOrCreate($this);
        $customerPricingPlan = UsagePricingPlan::where('tenant_id', $this)
            ->where('usage_type', UsageType::CustomersPerMonth->value)
            ->oneOrNull();
        $invoicePricingPlan = UsagePricingPlan::where('tenant_id', $this)
            ->where('usage_type', UsageType::InvoicesPerMonth->value)
            ->oneOrNull();

        return [
            'status' => $this->billingStatus()->value,
            'quota' => [
                'no_users' => $this->quota->get(QuotaType::Users),
                'no_customers' => $customerPricingPlan?->threshold ?? null,
                'no_invoices' => $invoicePricingPlan?->threshold ?? null,
            ],
            'provider' => $billingProfile->billing_system ?? 'null',
        ];
    }

    /**
     * Gets the `feature` property.
     */
    protected function getFeaturesValue(): FeatureCollection
    {
        if (!isset($this->_features)) {
            $this->_features = new FeatureCollection($this);
        }

        return $this->_features;
    }

    /**
     * Gets the `quota` property.
     */
    protected function getQuotaValue(): QuotaCollection
    {
        if (!isset($this->_quota)) {
            $this->_quota = new QuotaCollection($this);
        }

        return $this->_quota;
    }

    /**
     * Gets the `accounts_receivable_settings` property.
     */
    protected function getAccountsReceivableSettingsValue(): AccountsReceivableSettings
    {
        if (!$this->arSettings) {
            $this->arSettings = AccountsReceivableSettings::queryWithTenant($this)->oneOrNull();
            if (!$this->arSettings) {
                $this->arSettings = new AccountsReceivableSettings();
                $this->arSettings->tenant_id = (int) $this->id();
            }
        }

        return $this->arSettings;
    }

    /**
     * Gets the `accounts_payable_settings` property.
     */
    protected function getAccountsPayableSettingsValue(): AccountsPayableSettings
    {
        if (!$this->apSettings) {
            $this->apSettings = AccountsPayableSettings::queryWithTenant($this)->oneOrNull();
            if (!$this->apSettings) {
                $this->apSettings = new AccountsPayableSettings();
                $this->apSettings->tenant_id = (int) $this->id();
            }
        }

        return $this->apSettings;
    }

    /**
     * Gets the `cash_application_settings` property.
     */
    protected function getCashApplicationSettingsValue(): CashApplicationSettings
    {
        if (!$this->cashAppSettings) {
            $this->cashAppSettings = CashApplicationSettings::queryWithTenant($this)->oneOrNull();
            if (!$this->cashAppSettings) {
                $this->cashAppSettings = new CashApplicationSettings();
                $this->cashAppSettings->tenant_id = (int) $this->id();
            }
        }

        return $this->cashAppSettings;
    }

    /**
     * Gets the `customer_portal_settings` property.
     */
    protected function getCustomerPortalSettingsValue(): CustomerPortalSettings
    {
        if (!$this->customerPortalSettings) {
            $this->customerPortalSettings = CustomerPortalSettings::queryWithTenant($this)->oneOrNull();
            if (!$this->customerPortalSettings) {
                $this->customerPortalSettings = new CustomerPortalSettings();
                $this->customerPortalSettings->tenant_id = (int) $this->id();
            }
        }

        return $this->customerPortalSettings;
    }

    /**
     * Gets the `subscription_billing_settings` property.
     */
    protected function getSubscriptionBillingSettingsValue(): SubscriptionBillingSettings
    {
        if (!$this->subscriptionBillingSettings) {
            $this->subscriptionBillingSettings = SubscriptionBillingSettings::queryWithTenant($this)->oneOrNull();
            if (!$this->subscriptionBillingSettings) {
                $this->subscriptionBillingSettings = new SubscriptionBillingSettings();
                $this->subscriptionBillingSettings->tenant_id = (int) $this->id();
            }
        }

        return $this->subscriptionBillingSettings;
    }

    /**
     * Gets the `dashboard_api_key` property.
     */
    protected function getDashboardApiKeyValue(): string
    {
        $source = ApiKey::SOURCE_DASHBOARD;
        $user = UserContextFacade::get()->get();
        $expires = strtotime('+30 minutes');

        return $this->getProtectedApiKey($source, $user, $expires)
            ->secret;
    }

    /**
     * Gets the `currencies` property.
     */
    protected function getCurrenciesValue(): array
    {
        return $this->getCurrencies();
    }

    /**
     * Gets the `permissions` property.
     */
    protected function getPermissionsValue(): array
    {
        $requester = ACLModelRequester::get();
        if (!$requester instanceof Member) {
            return [];
        }

        return $requester->role()->permissions();
    }

    //
    // Validators
    //

    /**
     * Checks if a company username is valid and unique.
     */
    public static function validateUsername(string $username): bool
    {
        if (!preg_match('/^[A-Za-z0-9]*$/', $username) || strlen($username) < 4) {
            return false;
        }

        // check if username is protected
        return !in_array($username, self::PROTECTED_USERNAMES);
    }

    /**
     * Checks if a company username is valid and unique.
     */
    public static function validateEmail(string $email): bool
    {
        return !preg_match('/.*invoicedmail.com$/', $email);
    }

    /**
     * Checks if a company highlight color is valid.
     */
    public static function validateHighlightColor(string $color): bool
    {
        try {
            $hc = new HighlightColor($color);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    //
    // Relationships
    //

    /**
     * Gets the creator.
     */
    public function creator(): ?User
    {
        return $this->relation('creator_id');
    }

    //
    // Getters
    //

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['url'] = $this->url;

        return $result;
    }

    /**
     * Gets the default locale for this company.
     */
    public function getLocale(): string
    {
        return $this->language.'_'.$this->country;
    }

    /**
     * Generates the address for the company.
     *
     * @param bool $showCountry whether the country should be shown
     * @param bool $showName    whether the name should be shown
     */
    public function address($showCountry = false, $showName = true): string
    {
        $af = new AddressFormatter();

        return $af->setFrom($this)->format([
            'showCountry' => $showCountry,
            'showName' => $showName,
        ]);
    }

    /**
     * Gets the company's name.
     *
     * @param bool $notused
     */
    public function name($notused = false): string
    {
        return $this->name;
    }

    public function isMember(User $user): bool
    {
        if ($this->id() <= 0) {
            return false;
        }

        if ($user->id() <= 0) {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM Members WHERE tenant_id = :tenantId AND user_id = :userId';

        return self::getDriver()->getConnection(null)->fetchOne($sql, [
            'tenantId' => $this->id(),
            'userId' => $user->id(),
        ]) > 0;
    }

    /**
     * Checks if a member has permission to modify the settings
     * of this company.
     */
    public function memberCanEdit(Member $member): bool
    {
        if (!$member->allowed('settings.edit')) {
            return false;
        }

        return $member->tenant_id == $this->id;
    }

    /**
     * Checks if a member has permission to access billing of the company
     */
    public function memberCanAccessBusinessBilling(Member $member): bool
    {
        if (!$member->allowed('business.billing')) {
            return false;
        }

        return $member->tenant_id == $this->id;
    }

    /**
     * Sets the timezone used by php date/time functions to the timezone of the company.
     */
    public function useTimezone(): void
    {
        $tz = $this->time_zone ?: 'UTC';

        // skip if not changing time zones
        if (date_default_timezone_get() == $tz) {
            return;
        }

        date_default_timezone_set($tz);

        // sync up the db with the time zone
        try {
            // NOTE: we are using the name of the timezone (i.e. America/Chicago)
            // instead of the offset (i.e. -5:00) because the named time zone
            // correctly handles Daylight Savings Time
            self::getDriver()->getConnection(null)->executeStatement("SET time_zone='$tz';");
        } catch (Throwable $e) {
            LoggerFacade::get()->error('Unable to use company timezone', ['exception' => $e]);
        }
    }

    /**
     * Gets the default theme.
     */
    public function defaultTheme(): Theme
    {
        if (!$this->_defaultTheme && $tid = $this->accounts_receivable_settings->default_theme_id) {
            $this->_defaultTheme = Theme::queryWithTenant($this)
                ->where('id', $tid)
                ->oneOrNull();
        }

        if (!$this->_defaultTheme) {
            $this->_defaultTheme = new Theme(['tenant_id' => $this->id(), 'id' => null]);
        }

        return $this->_defaultTheme;
    }

    /**
     * Gets the money formatting options for this company.
     */
    public function moneyFormat(): array
    {
        if (!isset($this->_moneyFormat)) {
            $this->_moneyFormat = [
                'locale' => $this->getLocale(),
                'use_symbol' => !$this->show_currency_code,
            ];
        }

        return $this->_moneyFormat;
    }

    /**
     * Generates or locates a protected API key for this company.
     *
     * @param string   $source  type of API key to get
     * @param int|null $expires expiry timestamp
     */
    public function getProtectedApiKey(string $source, User $user = null, ?int $expires = null, bool $rememberMe = false): ApiKey
    {
        if ($user && $user->id() <= 0) {
            throw new InvalidArgumentException('Invalid user ID when creating protected API key: '.$user->id());
        }

        $k = $source;
        if ($user) {
            $k .= ':'.$user->id();
        }

        if (!isset($this->_protectedApiKeys[$k])) {
            $query = ApiKey::queryWithTenant($this)
                ->where('protected', true)
                ->where('source', $source)
                ->where('(`expires` IS NULL OR `expires` > '.time().')');

            if ($user) {
                $query->where('user_id', $user);
            }

            $key = $query->oneOrNull();
            if ($key) {
                $this->_protectedApiKeys[$k] = $key;
            }
        }

        if (!isset($this->_protectedApiKeys[$k])) {
            $key = new ApiKey();
            $key->tenant_id = (int) $this->id();
            $key->protected = true;
            $key->source = $source;
            $key->expires = $expires;
            $key->remember_me = $rememberMe;

            if ($user) {
                $key->user_id = (int) $user->id();
            }

            $key->saveOrFail();

            $this->_protectedApiKeys[$k] = $key;
        }

        return $this->_protectedApiKeys[$k];
    }

    /**
     * Gets the currencies used by this company's account.
     */
    public function getCurrencies(): array
    {
        // check if multi-currency is enabled
        if (!$this->features->has('multi_currency')) {
            return [$this->currency];
        }

        // check for a cached currencies list
        $k = 'company_currencies.'.$this->id();

        return CacheFacade::get()->get($k, function (ItemInterface $item) {
            $currencies = [$this->currency];

            // look up currencies used
            $sql = 'SELECT currency FROM Invoices WHERE tenant_id = :tenantId GROUP BY currency';
            $invoiceCurrencies = self::getDriver()->getConnection(null)->fetchFirstColumn($sql, ['tenantId' => $this->id()]);

            $currencies = array_unique(array_merge($currencies, $invoiceCurrencies));
            sort($currencies);

            // cache it for 1 day
            $item->expiresAfter(86400);

            return $currencies;
        });
    }

    /**
     * Clears the currencies cache used by this company's account.
     */
    public function clearCurrenciesCache(): void
    {
        $k = 'company_currencies.'.$this->id();
        CacheFacade::get()->delete($k);
    }

    /**
     * Retrieves the billing subscription status for this model.
     */
    public function billingStatus(): BillingSubscriptionStatus
    {
        return BillingSubscriptionStatusGenerator::get($this);
    }

    //
    // SendableDocumentInterface
    //

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new CompanyEmailVariables($this);
    }

    //
    // ThemeableInterface
    //

    public function theme(): Theme
    {
        return $this->defaultTheme();
    }

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new CompanyPdfVariables($this);
    }

    public function getThemeCompany(): Company
    {
        return $this;
    }

    //
    // IsEmailParticipantInterface
    //

    public function tenant(): Company
    {
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
