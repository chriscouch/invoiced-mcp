<?php

namespace App\AccountsReceivable\Models;

use App\AccountsReceivable\EmailVariables\CustomerEmailVariables;
use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Pdf\CustomerPdfVariables;
use App\Chasing\CustomerChasing\CustomerCadenceAssigner;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\LateFeeSchedule;
use App\Companies\Libs\NumberingSequence;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Traits\HasAutoNumberingTrait;
use App\Core\Authentication\Models\User;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Countries;
use App\Core\I18n\Currencies;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Search\Libs\SearchFacade;
use App\Core\Search\Traits\SearchableTrait;
use App\Core\Utils\AppUrl;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\Traits\HasClientIdTrait;
use App\Core\Utils\Traits\HasModelLockTrait;
use App\CustomerPortal\Models\SignUpPage;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\Traits\EventModelTrait;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpoolFacade;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Metadata\Traits\MetadataTrait;
use App\Network\Models\NetworkConnection;
use App\Notifications\Models\NotificationSubscription;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Interfaces\HasPaymentSourceInterface;
use App\PaymentProcessing\Libs\GetPaymentInfo;
use App\PaymentProcessing\Libs\VaultPaymentInfoFacade;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\SalesTax\Models\TaxRate;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\IsEmailParticipantInterface;
use App\Sending\Email\Libs\EmailSpoolFacade;
use App\Sending\Email\Traits\IsEmailParticipantTrait;
use App\SubscriptionBilling\Models\Subscription;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Interfaces\ThemeableInterface;
use App\Themes\Traits\ThemeableTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use ICanBoogie\Inflector;

/**
 * @property int                    $id
 * @property string                 $name
 * @property string|null            $email
 * @property string                 $type
 * @property string|null            $language
 * @property int|null               $owner_id
 * @property User|null              $owner
 * @property bool                   $autopay
 * @property string                 $collection_mode
 * @property string|null            $currency
 * @property string|null            $payment_terms
 * @property int                    $autopay_delay_days
 * @property int|null               $default_source_id
 * @property string|null            $default_source_type
 * @property PaymentSource|null     $payment_source
 * @property int|null               $sign_up_page_id
 * @property int|null               $sign_up_page
 * @property int|null               $parent_customer
 * @property bool                   $chase
 * @property int|null               $chasing_cadence_id
 * @property int|null               $chasing_cadence
 * @property int|null               $next_chase_step_id
 * @property int|null               $next_chase_step
 * @property bool                   $credit_hold
 * @property float|null             $credit_limit
 * @property bool                   $consolidated
 * @property bool                   $bill_to_parent
 * @property string|null            $attention_to
 * @property string|null            $address1
 * @property string|null            $address2
 * @property string|null            $city
 * @property string|null            $state
 * @property string|null            $postal_code
 * @property string                 $country
 * @property string                 $address
 * @property string|null            $phone
 * @property string|null            $notes
 * @property float                  $credit_balance
 * @property bool                   $taxable
 * @property array                  $taxes
 * @property string|null            $tax_id
 * @property string|null            $avalara_exemption_number
 * @property string|null            $avalara_entity_use_code
 * @property string|null            $statement_url
 * @property string|null            $statement_pdf_url
 * @property string|null            $sign_up_url
 * @property int|null               $ach_gateway_id
 * @property MerchantAccount|null   $ach_gateway
 * @property int|null               $cc_gateway_id
 * @property MerchantAccount|null   $cc_gateway
 * @property bool                   $convenience_fee
 * @property bool                   $surcharging
 * @property bool                   $active
 * @property int|null               $late_fee_schedule_id
 * @property LateFeeSchedule|null   $late_fee_schedule
 * @property NetworkConnection|null $network_connection
 * @property int|null               $network_connection_id
 */
class Customer extends AccountingWritableModel implements EventObjectInterface, MetadataModelInterface, ThemeableInterface, IsEmailParticipantInterface, HasPaymentSourceInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use HasAutoNumberingTrait;
    use HasClientIdTrait;
    use IsEmailParticipantTrait;
    use MetadataTrait;
    use SearchableTrait;
    use ThemeableTrait;
    use HasModelLockTrait;

    private ?array $_savePaymentSource = null;
    private ?PaymentSource $_paymentSource = null;
    private array $_moneyFormat;
    private ?array $_aging = null;
    private bool $_resetAutopayInvoices = false;
    private bool $ignoreUnsaved = false;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'number' => new Property(
                validate: ['string', 'min' => 1, 'max' => 32],
            ),
            'email' => new Property(
                null: true,
                validate: 'email',
            ),
            'type' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['company', 'government', 'non_profit', 'person']],
            ),
            'language' => new Property(
                null: true,
                validate: ['string', 'min' => 2, 'max' => 2],
            ),
            'owner' => new Property(
                null: true,
                belongs_to: User::class,
            ),
            'active' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),

            /* Billing Settings */

            'autopay' => new Property(
                type: Type::BOOLEAN,
            ),
            // TODO deprecated
            'collection_mode' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['auto', 'manual']],
                default: AccountsReceivableSettings::COLLECTION_MODE_MANUAL,
                in_array: false,
            ),
            'currency' => new Property(
                null: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency'], 'nullable' => true],
            ),
            'payment_terms' => new Property(
                null: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'autopay_delay_days' => new Property(
                type: Type::INTEGER,
                default: -1,
            ),
            'default_source_id' => new Property(
                null: true,
                in_array: false,
            ),
            'default_source_type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['card', 'bank_account']],
                in_array: false,
            ),
            'sign_up_page_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: SignUpPage::class,
            ),
            'parent_customer' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: self::class,
            ),

            /* Payment Settings */

            'ach_gateway' => new Property(
                null: true,
                belongs_to: MerchantAccount::class,
            ),
            'cc_gateway' => new Property(
                null: true,
                belongs_to: MerchantAccount::class,
            ),
            'convenience_fee' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'surcharging' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),

            /* Chasing */

            'chase' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'chasing_cadence_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: ChasingCadence::class,
            ),
            'chasing_cadence' => new Property(
                relation: ChasingCadence::class,
                local_key: 'chasing_cadence_id',
            ),
            'next_chase_step_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: ChasingCadenceStep::class,
            ),
            'next_chase_step' => new Property(
                relation: ChasingCadenceStep::class,
                local_key: 'next_chase_step_id',
            ),

            /* Credit Holds / Limits */

            'credit_hold' => new Property(
                type: Type::BOOLEAN,
            ),
            'credit_limit' => new Property(
                type: Type::FLOAT,
                null: true,
            ),

            /* Invoice Consolidation */

            'consolidated' => new Property(
                type: Type::BOOLEAN,
            ),
            'bill_to_parent' => new Property(
                type: Type::BOOLEAN,
            ),

            /* Address */

            'attention_to' => new Property(
                null: true,
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
                validate: ['callable', 'fn' => [Countries::class, 'validateCountry']],
            ),

            /* Extra Info */

            'phone' => new Property(
                null: true,
            ),
            'notes' => new Property(
                null: true,
            ),

            /* Calculated */

            'credit_balance' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),

            /* Taxes */

            'taxable' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'taxes' => new Property(
                type: Type::ARRAY,
                validate: ['callable', 'fn' => [self::class, 'validateTaxes']],
                default: [],
            ),
            'tax_id' => new Property(
                null: true,
            ),
            'avalara_exemption_number' => new Property(
                null: true,
            ),
            'avalara_entity_use_code' => new Property(
                null: true,
            ),

            /* Late Fee */
            'late_fee_schedule' => new Property(
                null: true,
                belongs_to: LateFeeSchedule::class,
            ),

            /* Network */

            'network_connection' => new Property(
                null: true,
                belongs_to: NetworkConnection::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'setDefaultEntityType']);
        self::creating([self::class, 'setDefaultLateFeeSchedule']);
        self::creating([self::class, 'inheritFromCompany'], 2);
        self::creating([self::class, 'validateAutoPay'], 2);
        self::saving([self::class, 'verifyParentCustomer'], 2);
        self::saving([self::class, 'verifyOwner'], 2);
        self::creating([self::class, 'capturePaymentSource'], 2);
        self::creating([self::class, 'dedupeTaxes'], 2);
        self::saving([self::class, 'assignChasingCadence'], 2);
        self::saving([self::class, 'verifyChasingCadence'], 2);
        self::saving([self::class, 'verifyNetworkConnection'], 2);
        self::saving([self::class, 'setDefaultCountry'], 2);

        self::updating([self::class, 'beforeUpdate'], -512);

        self::created([self::class, 'savePaymentSourceAfterCreate']);

        self::updated([self::class, 'resetAutopayInvoices']);

        self::deleting([self::class, 'beforeDelete']);

        parent::initialize();
    }

    protected function getMassAssignmentBlocked(): ?array
    {
        return ['client_id', 'client_id_exp', 'credit_balance', 'object', 'statement_pdf_url', 'sign_up_url', 'created_at', 'updated_at'];
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        // Limit the result set for the member's customer restrictions.
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $requester->restriction_mode) {
                if ($restrictions = $requester->restrictions()) {
                    $queryBuilder = new RestrictionQueryBuilder($requester->tenant(), $restrictions);
                    $queryBuilder->addToOrmQuery('id', $query);
                }
            } elseif (Member::OWNER_RESTRICTION == $requester->restriction_mode) {
                // WARNING: Do not use this format:
                //   $query->where('owner_id', $requester->user_id)
                // The reason is that it is possible for an API query filter
                // condition to overwrite it. A SQL query fragment prevents this.
                $query->where('owner_id = '.$requester->user_id);
            }
        }

        return $query;
    }

    //
    // Hooks
    //

    public static function setDefaultEntityType(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->type) {
            $model->type = $model->tenant()->accounts_receivable_settings->default_customer_type;
        }
    }

    public static function setDefaultLateFeeSchedule(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->dirty('late_fee_schedule')) {
            $model->late_fee_schedule = LateFeeSchedule::where('default', true)
                ->oneOrNull();
        }
    }

    /**
     * Captures the default payment source before saving.
     */
    public static function inheritFromCompany(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $company = $model->tenant();

        // inherit AutoPay from settings
        if (!$model->dirty('autopay')) {
            $model->autopay = AccountsReceivableSettings::COLLECTION_MODE_AUTO == $company->accounts_receivable_settings->default_collection_mode;
        }

        // inherit payment terms from settings
        if (!$model->autopay && !$model->dirty('payment_terms')) {
            $model->payment_terms = $company->accounts_receivable_settings->payment_terms;
        }

        // inherit consolidated invoicing from settings
        if (!$model->consolidated && !$model->dirty('consolidated')) {
            $model->consolidated = $company->accounts_receivable_settings->default_consolidated_invoicing;
        }
    }

    /**
     * Set by default the country to tenant's country if not set
     */
    public static function setDefaultCountry(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $company = $model->tenant();

        if (!$model->country) {
            $model->country = (string) $company->country;
        }
    }

    /**
     * Validates that the company supports AutoPay when enabled.
     */
    public static function validateAutoPay(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->autopay) {
            $company = $model->tenant();
            if (!PaymentMethod::acceptsAutoPay($company)) {
                throw new ListenerException('autopay_not_supported', ['field' => 'autopay']);
            }

            // AutoPay does not have payment terms
            $model->payment_terms = null;
        }
    }

    /**
     * Validates that the owner on the company is a valid selection.
     */
    public static function verifyParentCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (!$model->parent_customer) {
            return;
        }

        // only validate if the parent customer is changing
        if ($model->parent_customer == $model->ignoreUnsaved()->parent_customer) {
            return;
        }

        // verify the customer exists
        $customer = $model->parentCustomer();
        if (!$customer) {
            throw new ListenerException('No such customer: '.$model->owner, ['field' => 'parent_customer']);
        }

        // verify parent customer is not self
        if ($model->id() === $customer->id()) {
            throw new ListenerException('Cannot set parent customer as self.', ['field' => 'parent_customer']);
        }

        // verify the parent customer does not create a cycle
        if ($model->isParentOf($customer)) {
            throw new ListenerException('Cannot set sub-customer as a parent.', ['field' => 'parent_customer']);
        }

        // verify hierarchy depth
        if ($model->parentExceedsMaxDepth($customer)) {
            throw new ListenerException('A customer cannot have more than five levels of sub-customers.', ['field' => 'parent_customer']);
        }
    }

    /**
     * Validates that the owner on the company is a valid selection.
     */
    public static function verifyOwner(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (isset($model->owner)) {
            unset($model->owner);
        }

        if (!$model->owner_id) {
            return;
        }

        // only validate if the owner is changing
        if ($model->owner_id == $model->ignoreUnsaved()->owner_id) {
            return;
        }

        // verify the user exists
        $owner = $model->owner;
        if (!$owner) {
            throw new ListenerException('No such user: '.$model->owner, ['field' => 'owner']);
        }

        // verify the user is a member
        if (!$model->tenant()->isMember($owner)) {
            throw new ListenerException('User is not a member of this company: '.$model->owner, ['field' => 'owner']);
        }
    }

    /**
     * Captures the default payment source before saving.
     */
    public static function capturePaymentSource(AbstractEvent $event): void
    {
        // DEPRECATED the stripe token method is deprecated
        /** @var self $model */
        $model = $event->getModel();
        if (isset($model->stripe_token) && $stripeToken = $model->stripe_token) {
            $model->_savePaymentSource = [
                'method' => PaymentMethod::CREDIT_CARD,
                'gateway_token' => $stripeToken,
            ];
            unset($model->stripe_token);
        }
    }

    /**
     * Deduplicate the tax rates.
     */
    public static function dedupeTaxes(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $taxes = $model->taxes;
        if (is_array($taxes)) {
            $model->taxes = array_unique($taxes);
        }
    }

    /**
     * Sets the default payment source.
     */
    public static function savePaymentSourceAfterCreate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        try {
            if ($model->_savePaymentSource) {
                $model->savePaymentSource();
            }
        } catch (PaymentSourceException $e) {
            // if saving the token fails then rollback
            // the entire transaction
            $model->rollback();

            throw new ListenerException($e->getMessage(), ['field' => 'payment_source']);
        }
    }

    /**
     * Resets AutoPay on invoice level, if customer's AutoPay is disabled.
     *
     * @throws Exception
     */
    public static function resetAutopayInvoices(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->_resetAutopayInvoices) {
            return;
        }

        try {
            Invoice::where('customer', $model->id)
                ->where('paid', false)
                ->where('closed', false)
                ->where('voided', false)
                ->where('autopay', true)
                ->set([
                    'autopay' => false,
                    'payment_terms' => '',
                ]);
        } catch (ModelException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'autopay']);
        }
        $model->_resetAutopayInvoices = false;
    }

    /**
     * Verifies the chasing cadence relationship when saving.
     */
    public static function verifyChasingCadence(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (isset($model->chasing_cadence)) {
            unset($model->chasing_cadence);
        }

        if (isset($model->next_chase_step)) {
            unset($model->next_chase_step);
        }

        // verify the chasing cadence
        $cid = $model->chasing_cadence;
        if (!$cid) {
            return;
        }

        $cadence = $model->chasingCadence();
        if (!$cadence) {
            throw new ListenerException("No such chasing cadence: $cid", ['field' => 'chasing_cadence']);
        }

        // verify the next step
        $sid = $model->next_chase_step_id;
        if (!$sid) {
            return;
        }

        $cadence = $model->nextChaseStep();
        if (!$cadence) {
            throw new ListenerException("No such chasing cadence step: $sid", ['field' => 'chasing_cadence_step']);
        }
    }

    public static function assignChasingCadence(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->chasing_cadence_id || !$model->chase) {
            return;
        }

        $assigner = new CustomerCadenceAssigner($model->tenant());
        $cadence = $assigner->assign($model);

        if ($cadence) {
            $model->chasing_cadence_id = (int) $cadence->id();
            $model->next_chase_step_id = (int) $cadence->getSteps()[0]->id();
            $model->chase = true;
        }
    }

    /**
     * Verifies the network connection relationship when saving.
     */
    public static function verifyNetworkConnection(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $connection = $model->network_connection;
        if ($connection && $connection->vendor_id != $model->tenant_id) {
            throw new ListenerException('Network connection not found: '.$connection->id);
        }
    }

    public static function beforeUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // check the number is unique
        $customerNumber = trim(strtolower((string) $model->number));
        $previousNumber = trim(strtolower((string) $model->ignoreUnsaved()->number));
        if ($customerNumber && $customerNumber != $previousNumber) {
            if (!$model->getNumberingSequence()->isUnique($model->number)) {
                $name = strtolower(Inflector::get()->titleize(static::modelName()));
                throw new ListenerException('The given '.$name.' number has already been taken: '.$model->number, ['field' => 'number']);
            }
        }

        // validate that AutoPay supported
        if ($model->dirty('autopay', true)) {
            if ($model->autopay) {
                $company = $model->tenant();
                if (!PaymentMethod::acceptsAutoPay($company)) {
                    throw new ListenerException('autopay_not_supported', ['field' => 'autopay']);
                }

                // AutoPay does not have payment terms
                $model->payment_terms = null;
            } else {
                $model->_resetAutopayInvoices = true;
            }
        }

        // deduplicate taxes
        if ($model->dirty('taxes') && is_array($model->taxes)) {
            $model->taxes = array_unique($model->taxes);
        }

        // update default payment source
        // DEPRECATED the stripe token method is deprecated
        if (isset($model->stripe_token)) {
            $model->_savePaymentSource = [
                'method' => PaymentMethod::CREDIT_CARD,
                'gateway_token' => $model->stripe_token,
            ];
            unset($model->stripe_token);
        }

        // save the payment source now, before the save
        // to catch any potential errors with the payment info
        try {
            if ($model->_savePaymentSource) {
                $model->savePaymentSource();
            }
        } catch (PaymentSourceException $e) {
            throw new ListenerException($e->getMessage(), ['field' => 'payment_source']);
        }

        // clear any cached payment source
        $model->_paymentSource = null;

        // validate customer active status
        if ($model->dirty('active') && !$model->active) {
            if ($model->hasActiveSubscription()) {
                throw new ListenerException('Customers with an active subscription cannot be deactivated', ['field' => 'active']);
            }
        }
    }

    public static function beforeDelete(AbstractEvent $event): void
    {
        /** @var Customer $model */
        $model = $event->getModel();

        if ($model->hasTransactions()) {
            throw new ListenerException('Customers with transactions cannot be deleted');
        }

        if ($model->hasActiveSubscription()) {
            throw new ListenerException('Customers with an active subscription cannot be deleted');
        }
    }

    // we need to locally preserve ignore unsaved to be able to use
    // it to fetch payment source
    public function ignoreUnsaved(): static
    {
        $this->ignoreUnsaved = true;

        return parent::ignoreUnsaved();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['statement_url'] = $this->statement_url;
        $result['statement_pdf_url'] = $this->statement_pdf_url;
        $result['sign_up_url'] = $this->sign_up_url;
        // toArray method above resets ignore unsaved flag to false
        // but we need to use it here, so we do workaround
        // wrapper
        if ($this->ignoreUnsaved) {
            $type = $this->ignoreUnsaved()->default_source_type;
            $id = $this->ignoreUnsaved()->default_source_id;

            // we reset ignore unsaved after we fetched the data
            $this->ignoreUnsaved = false;
        } else {
            $type = $this->default_source_type;
            $id = $this->default_source_id;
        }
        $paymentSource = $this->getPaymentSource($type, $id);
        $result['payment_source'] = $paymentSource?->toArray();
        $result['sign_up_page'] = $this->sign_up_page;
        $result['metadata'] = $this->metadata;
        $result['ach_gateway_object'] = $this->ach_gateway?->toArray();
        $result['cc_gateway_object'] = $this->cc_gateway?->toArray();

        $this->toArrayHook($result, [], [], []);

        return $result;
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        if ($this->_noArrayHook) {
            $this->_noArrayHook = false;

            return;
        }

        // taxes
        $result['taxes'] = TaxRate::expandList((array) $result['taxes']);
    }

    //
    // Mutators
    //
    /**
     * Sets the country property.
     */
    protected function setCountryValue(mixed $country): ?string
    {
        if (!$country) {
            return null;
        }

        return strtoupper($country);
    }

    /**
     * Sets the language property.
     */
    protected function setLanguageValue(mixed $language): ?string
    {
        if (!$language) {
            return null;
        }

        return strtolower($language);
    }

    /**
     * Sets the `autopay` value.
     */
    protected function setAutopayValue(mixed $enabled): mixed
    {
        if ($enabled) {
            $this->collection_mode = AccountsReceivableSettings::COLLECTION_MODE_AUTO;
        } else {
            $this->collection_mode = AccountsReceivableSettings::COLLECTION_MODE_MANUAL;
        }

        return $enabled;
    }

    /**
     * Sets the external_id property. `external_id` is currently an
     * alias for `number`.
     */
    public function setExternalIdValue(mixed $value): void
    {
        // external_id is currently an alias for number
        $this->number = $value;
    }

    /**
     * Sets the sign_up_page value.
     */
    protected function setSignUpPageValue(mixed $id): ?int
    {
        $this->sign_up_page_id = $id;

        return $id;
    }

    /**
     * Sets the chasing_cadence value.
     */
    protected function setChasingCadenceValue(mixed $id): ?int
    {
        $this->chasing_cadence_id = $id;

        return $id;
    }

    /**
     * Sets the next_chase_step value.
     */
    protected function setNextChaseStepValue(mixed $value): ?int
    {
        if (is_object($value) && isset($value->id)) {
            $value = $value->id;
        }

        $this->next_chase_step_id = $value;

        return $value;
    }

    /**
     * Sets the payment_source value.
     */
    protected function setPaymentSourceValue(mixed $parameters): mixed
    {
        if (is_array($parameters)) {
            $this->_savePaymentSource = $parameters;
        }

        return null;
    }

    //
    // Accessors
    //

    /**
     * Gets the `collection_mode` property.
     */
    protected function getCollectionModeValue(): string
    {
        if ($this->autopay) {
            return AccountsReceivableSettings::COLLECTION_MODE_AUTO;
        }

        return AccountsReceivableSettings::COLLECTION_MODE_MANUAL;
    }

    /**
     * Gets the statement_url property.
     */
    protected function getStatementUrlValue(): ?string
    {
        if (!$this->client_id) {
            return null;
        }

        return AppUrl::get()->build().'/statements/'.$this->tenant()->identifier.'/'.$this->client_id;
    }

    /**
     * Gets the statement_pdf_url property.
     */
    protected function getStatementPdfUrlValue(): ?string
    {
        if (!$this->client_id) {
            return null;
        }

        return AppUrl::get()->build().'/statements/'.$this->tenant()->identifier.'/'.$this->client_id.'/pdf';
    }

    /**
     * Gets the sign_up_url property.
     */
    protected function getSignUpUrlValue(): ?string
    {
        if (!$this->sign_up_page_id) {
            return null;
        }

        return $this->tenant()->url.'/sign_up/'.$this->client_id;
    }

    /**
     * Gets the chasing_cadence property.
     */
    protected function getChasingCadenceValue(): ?int
    {
        return $this->chasing_cadence_id;
    }

    /**
     * Gets the next_chase_step property.
     */
    protected function getNextChaseStepValue(): ?int
    {
        return $this->next_chase_step_id;
    }

    /**
     * Gets the payment_source property (if a source exists).
     */
    protected function getPaymentSourceValue(): ?PaymentSource
    {
        if (!isset($this->_paymentSource) || ($this->_paymentSource instanceof PaymentSource && $this->default_source_id != $this->_paymentSource->id())) {
            $this->_paymentSource = $this->getPaymentSource($this->default_source_type, $this->default_source_id);
        }

        return $this->_paymentSource;
    }

    /**
     * Gets the payment_source property (if a source exists).
     */
    protected function getPaymentSource(?string $type, ?int $id): ?PaymentSource
    {
        if (ObjectType::BankAccount->typeName() === $type) {
            return BankAccount::where('id', $id)
                ->where('chargeable', true)
                ->oneOrNull();
        }
        if (ObjectType::Card->typeName() === $type) {
            return Card::where('id', $id)
                ->where('chargeable', true)
                ->oneOrNull();
        }

        return null;
    }

    /**
     * Gets the sign_up_page value.
     */
    protected function getSignUpPageValue(mixed $id): ?int
    {
        if (!$id) {
            return $this->sign_up_page_id;
        }

        return $id;
    }

    /**
     * Gets the formatted address with the `address` property.
     *
     * @param string|null $address
     */
    protected function getAddressValue($address): string
    {
        if ($address) {
            return $address;
        }

        return $this->address(false);
    }

    /**
     * Gets the customer `aging` property.
     * NOTE: this must be passed in first with setAging().
     */
    protected function getAgingValue(): ?array
    {
        return $this->_aging;
    }

    protected function getSubscribedValue(): ?bool
    {
        $requester = ACLModelRequester::get();
        if (!$requester instanceof Member) {
            return null;
        }
        $subscription = NotificationSubscription::where('member_id', $requester->id)
            ->where('customer_id', $this->id)
            ->oneOrNull();

        return $subscription ? $subscription->subscribe : $requester->subscribe_all;
    }

    //
    // Validators
    //

    /**
     * Validates tax input.
     */
    public static function validateTaxes(mixed $taxes): bool
    {
        if (!is_array($taxes)) {
            return false;
        }

        foreach ($taxes as $id) {
            if (!is_string($id) && !is_numeric($id)) {
                return false;
            }
        }

        return true;
    }

    //
    // Relationships
    //

    /**
     * Gets the parent customer.
     */
    public function parentCustomer(): ?self
    {
        return $this->relation('parent_customer');
    }

    /**
     * Gets the sign up page.
     */
    public function signUpPage(): ?SignUpPage
    {
        return $this->relation('sign_up_page_id');
    }

    /**
     * Gets the chasing cadence.
     */
    public function chasingCadence(): ?ChasingCadence
    {
        return $this->relation('chasing_cadence_id');
    }

    /**
     * Gets the next chasing step.
     */
    public function nextChaseStep(): ?ChasingCadenceStep
    {
        return $this->relation('next_chase_step_id');
    }

    //
    // Getters
    //

    /**
     * Gets the locale for this customer.
     */
    public function getLocale(): string
    {
        $language = $this->language;
        if (!$language) {
            $language = $this->tenant()->language;
        }

        $country = $this->country;

        return $language.($country ? '_'.$this->country : '');
    }

    /**
     * Gets the money formatting options for this customer.
     */
    public function moneyFormat(): array
    {
        if (!isset($this->_moneyFormat)) {
            $this->_moneyFormat = $this->tenant()->moneyFormat();
            $this->_moneyFormat['locale'] = $this->getLocale();
        }

        return $this->_moneyFormat;
    }

    /**
     * Generates the address for the customer.
     */
    public function address(bool $showName = true): string
    {
        // cannot generate an address if we do not know the country
        // inherit the company country, if set
        $companyCountry = $this->tenant()->country;
        if (!$this->country) {
            if (!$companyCountry) {
                return '';
            }

            $this->country = $companyCountry;
        }

        // only show the country line when the customer and
        // company are in different countries
        $showCountry = $this->country != $companyCountry;

        $af = new AddressFormatter();

        return $af->setFrom($this)->format([
            'showCountry' => $showCountry,
            'showName' => $showName,
        ]);
    }

    /**
     * Gets the bill to customer for this customer using
     * the customer hierarchy and bill to setting.
     */
    public function getBillToCustomer(): self
    {
        if ($this->bill_to_parent && $parentCustomer = $this->parentCustomer()) {
            return $parentCustomer->getBillToCustomer();
        }

        return $this;
    }

    /**
     * Gets the primary contacts for this customer.
     *
     * @param bool $primary whether primary contacts only should be located
     *
     * @return Contact[]
     */
    public function contacts(bool $primary = true): array
    {
        $query = Contact::where('customer_id', $this->id())
            ->sort('name ASC');

        if ($primary) {
            $query->where('primary', true);
        }

        $contacts = $query->all();

        $hasCustomerEmail = false;
        $result = [];
        foreach ($contacts as $contact) {
            $result[] = $contact;
            $email = trim(strtolower((string) $contact->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && $email == $this->email) {
                $hasCustomerEmail = true;
            }
        }

        // ensure there is at least 1 contact (include customer profile)
        if (!$hasCustomerEmail) {
            $contact = new Contact();
            $contact->customer = $this;
            $contact->name = $this->name;
            $contact->email = $this->email;
            $contact->primary = true;
            $result[] = $contact;
        }

        return $result;
    }

    /**
     * Gets the primary email contacts for this customer.
     */
    public function emailContacts(): array
    {
        return $this->extractEmails($this->contacts());
    }

    /**
     * Extracts valid email contacts from a list of Contact objects.
     */
    private function extractEmails(array $contacts): array
    {
        $return = [];
        foreach ($contacts as $contact) {
            $email = trim(strtolower((string) $contact->email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $return[] = [
                    'name' => $contact->name,
                    'email' => $email,
                ];
            }
        }

        return $return;
    }

    /**
     * Attempts to get a primary email address for a customer.
     */
    public function emailAddress(): ?string
    {
        if ($email = $this->email) {
            return $email;
        }

        $contacts = $this->emailContacts();
        if (count($contacts) > 0) {
            return $contacts[0]['email'];
        }

        return null;
    }

    /**
     * Gets the MerchantAccount for a payment method if
     * one has been set.
     */
    public function merchantAccount(string $method): ?MerchantAccount
    {
        return match ($method) {
            PaymentMethod::ACH => $this->ach_gateway,
            PaymentMethod::CREDIT_CARD => $this->cc_gateway,
            default => null,
        };
    }

    /**
     * Gets the customer's chargeable payment sources.
     *
     * @param bool $includeHidden
     *
     * @return PaymentSource[]
     */
    public function paymentSources($includeHidden = false): array
    {
        $info = new GetPaymentInfo();

        if ($includeHidden) {
            return $info->getAll($this);
        }

        return $info->getAllActive($this);
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new CustomerEmailVariables($this);
    }

    /**
     * Gets the primary currency for the customer.
     */
    public function calculatePrimaryCurrency(?int $start = null, ?int $end = null, bool $openOnly = false): string
    {
        // check if multi-currency is enabled
        /** @var Company $company */
        $company = $this->tenant();
        if (!$company->features->has('multi_currency')) {
            return $company->currency;
        }

        // used customer's currency property if set
        if ($currency = $this->currency) {
            return $currency;
        }

        // determine primary currency for this statement by looking at the most recently used invoice for this customer
        $sql = 'SELECT currency,COUNT(*) AS n FROM (SELECT currency FROM Invoices WHERE tenant_id=? AND customer=? AND draft=0 AND voided=0';
        $params = [$company->id(), $this->id()];

        if ($end && $start) {
            $sql .= ' AND `date` BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        } elseif ($end) {
            $sql .= ' AND `date` <= ?';
            $params[] = $end;
        } elseif ($start) {
            $sql .= ' AND `date` >= ?';
            $params[] = $start;
        }

        if ($openOnly) {
            $sql .= ' AND closed=0 AND paid=0';
        }

        $sql .= ' ORDER BY `date` DESC, id DESC LIMIT 1) i GROUP BY currency ORDER BY n DESC';

        $row = self::getDriver()->getConnection(null)->fetchAssociative($sql, $params);

        if (!$row) {
            return $company->currency;
        }

        // save the calculated currency
        EventSpool::disablePush();
        $this->currency = $row['currency'];
        $this->save();
        EventSpool::enablePop();

        return $this->currency;
    }

    /**
     * Checks if the given customer is a sub-customer
     * on ANY level.
     */
    public function isParentOf(Customer $customer): bool
    {
        // shortcut to scenario where input has no parent customer
        if (!$customer->parent_customer) {
            return false;
        }

        // shortcut to scenario where input is a direct descendant
        if ($customer->parent_customer == $this->id()) {
            return true;
        }

        /** @var Connection $db */
        $db = self::getDriver()->getConnection(null);
        $hierarchy = new CustomerHierarchy($db);

        return in_array($this->id(), $hierarchy->getParentIds($customer));
    }

    /**
     * Checks whether adding the provided as a parent will exceed
     * the customer hierarchy depth limit.
     */
    public function parentExceedsMaxDepth(Customer $customer): bool
    {
        /** @var Connection $db */
        $db = self::getDriver()->getConnection(null);
        $hierarchy = new CustomerHierarchy($db);
        $distanceFromTop = $hierarchy->getDepthFromRoot($customer);
        $distanceFromBottom = $hierarchy->getMaxDepthFromCustomer($this);

        return $distanceFromTop + $distanceFromBottom > CustomerHierarchy::MAX_DEPTH;
    }

    /**
     * Checks if the customer has any transactions.
     */
    public function hasTransactions(): bool
    {
        $models = [
            Invoice::class,
            CreditNote::class,
            Estimate::class,
        ];

        foreach ($models as $model) {
            $count = $model::where('customer', $this->id())->count();
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the customer has any active subscriptions.
     */
    public function hasActiveSubscription(): bool
    {
        return Subscription::where('customer', $this)
            ->where('finished = 0 AND canceled = 0')
            ->count() > 0;
    }

    //
    // Setters
    //

    /**
     * Rolls back a transaction.
     */
    private function rollback(): void
    {
        // clear any unwritten events / indexing operations
        EventSpoolFacade::get()->clear();
        SearchFacade::get()->clearIndexSpools();
        AccountingWriteSpoolFacade::get()->clear();
        EmailSpoolFacade::get()->clear();

        // reset the numbering sequences
        NumberingSequence::resetCache();
    }

    /**
     * Sets the customer's default payment source.
     */
    public function setDefaultPaymentSource(PaymentSource $source, ?DeletePaymentInfo $deletePaymentInfo = null): bool
    {
        // if the payment source is the same as the current
        // default, then we can move on
        if ($this->default_source_id == $source->id()) {
            return true;
        }

        $oldSource = $deletePaymentInfo ? $this->getPaymentSource($this->default_source_type, $this->default_source_id) : null;

        $this->default_source_type = $source->object;
        $this->default_source_id = (int) $source->id();

        $result = $this->save();

        $this->_paymentSource = $source;

        // delete the old source
        if ($deletePaymentInfo && $oldSource) {
            try {
                $deletePaymentInfo->delete($oldSource);
            } catch (PaymentSourceException) {
                // Exceptions when deleting the old payment method
                // are intentionally ignored.
            }
        }

        return $result;
    }

    /**
     * Clears the customer's default payment source.
     */
    public function clearDefaultPaymentSource(): bool
    {
        $this->default_source_type = null;
        $this->default_source_id = null;

        $result = $this->save();

        $this->_paymentSource = null;

        return $result;
    }

    /**
     * Sets the payment source.
     */
    public function setPaymentSource(PaymentSource $source): void
    {
        $this->_paymentSource = $source;
    }

    /**
     * Saves a customer's payment source.
     *
     * @throws PaymentSourceException when the payment source cannot be saved
     */
    private function savePaymentSource(): void
    {
        $parameters = $this->_savePaymentSource;
        $this->_savePaymentSource = null;
        if (!is_array($parameters)) {
            throw new PaymentSourceException('Invalid payment source format');
        }

        // determine the payment method (defaults to CC)
        $type = array_value($parameters, 'method');
        if (!$type) {
            $type = PaymentMethod::CREDIT_CARD;
        }
        unset($parameters['method']);
        $method = PaymentMethod::instance($this->tenant(), $type);

        VaultPaymentInfoFacade::get()->save($method, $this, $parameters);
    }

    public function setParentCustomer(Customer $customer): void
    {
        $this->parent_customer = (int) $customer->id();
        $this->setRelation('parent_customer', $customer);
    }

    /**
     * @return $this
     */
    public function setAging(array $aging)
    {
        $this->_aging = $aging;

        return $this;
    }

    //
    // ThemeableInterface
    //

    public function getThemeVariables(): PdfVariablesInterface
    {
        return new CustomerPdfVariables($this);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAccountingObjectReference(): InvoicedObjectReference
    {
        return new InvoicedObjectReference($this->object, (string) $this->id(), $this->name);
    }

    public function getPaymentSourceType(): ?string
    {
        return $this->default_source_type;
    }

    public function getPaymentSourceId(): ?int
    {
        return $this->default_source_id;
    }

    public function getAddress(): array
    {
        return [
            'address1' => $this->address1,
            'address2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];
    }
}
