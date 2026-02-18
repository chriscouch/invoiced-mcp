<?php

namespace App\PaymentProcessing\Models;

use App\Companies\Models\Company;
use App\Core\Entitlements\FeatureCollection;
use App\Core\I18n\TranslatorFacade;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Definition;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use InvalidArgumentException;

/**
 * This model represents a payment method that the merchant accepts.
 *
 * @property string      $id
 * @property string|null $name
 * @property bool        $enabled
 * @property int         $order
 * @property string|null $meta
 * @property string|null $gateway
 * @property int|null    $merchant_account_id
 * @property int|null    $merchant_account
 * @property int         $convenience_fee
 * @property int|null    $min
 * @property int|null    $max
 */
class PaymentMethod extends MultitenantModel
{
    use AutoTimestamps;

    const ACH = 'ach';
    const AFFIRM = 'affirm';
    const BALANCE = 'balance';
    const CASH = 'cash';
    const CHECK = 'check';
    const CREDIT_CARD = 'credit_card';
    const DIRECT_DEBIT = 'direct_debit';
    const KLARNA = 'klarna';
    const OTHER = 'other';
    const PAYPAL = 'paypal';
    const WIRE_TRANSFER = 'wire_transfer';

    /**
     * Contains the default list of payment methods
     * and their ordering on payment screens.
     */
    const METHODS = [
        PaymentMethodType::Card,
        PaymentMethodType::DirectDebit,
        PaymentMethodType::BankTransfer,
        PaymentMethodType::Online,
        PaymentMethodType::Ach,
        PaymentMethodType::PayPal,
        PaymentMethodType::Check,
        PaymentMethodType::WireTransfer,
        PaymentMethodType::Cash,
        PaymentMethodType::Other,
        PaymentMethodType::Affirm,
        PaymentMethodType::Klarna,
    ];

    private MerchantAccount $defaultMerchantAccount;

    protected static function getIDProperties(): array
    {
        return ['tenant_id', 'id'];
    }

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                required: true,
            ),
            'name' => new Property(
                null: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                validate: 'boolean',
            ),
            'order' => new Property(
                type: Type::INTEGER,
            ),
            'meta' => new Property(
                null: true,
            ),
            'gateway' => new Property(
                null: true,
            ),
            'merchant_account_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
                relation: MerchantAccount::class,
            ),
            'merchant_account' => new Property(
                null: true,
                relation: MerchantAccount::class,
                local_key: 'merchant_account_id',
            ),
            'min' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'max' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'convenience_fee' => new Property(
                type: Type::INTEGER,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'determineGateway']);
        self::updating([self::class, 'beforeUpdatePaymentMethod']);
        self::saving([self::class, 'validatePaymentLimits']);
        self::saving([self::class, 'validateConvenienceFee']);
        self::saving(function (AbstractEvent $event): void {
            /** @var self $model */
            $model = $event->getModel();
            if (isset($model->merchant_account)) {
                unset($model->merchant_account);
            }
        });
        self::deleting(function (AbstractEvent $event): never {
            throw new ListenerException('Payment methods cannot be deleted');
        });
        self::afterPersist(function (): void {
            FeatureCollection::clearCache();
        });

        parent::initialize();
    }

    public static function definition(): Definition
    {
        $definition = parent::definition();

        return $definition;
    }

    public static function determineGateway(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->gateway) {
            return;
        }

        if (self::PAYPAL == $model->id) {
            $model->gateway = PaymentGatewayMetadata::PAYPAL;
        }
    }

    public static function validatePaymentLimits(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // validate payment limits

        if ($model->min < 0) {
            throw new ListenerException('Minimum cannot be less than 0.', ['field' => 'min']);
        }

        if ($model->max < 0) {
            throw new ListenerException('Maximum cannot be less than 0.', ['field' => 'max']);
        }

        if ($model->min >= $model->max && null !== $model->max) {
            throw new ListenerException('Minimum must be less than maximum.', ['field' => 'min']);
        }

        if ($model->max > 1000000000) {
            throw new ListenerException('Maximum can be no more than 10000000.', ['field' => 'max']);
        }
    }

    public static function validateConvenienceFee(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->convenience_fee < 0 || $model->convenience_fee > 400) {
            throw new ListenerException('Convenience fee should be between 0 and 4%.', ['field' => 'convenience_fee']);
        }
    }

    public static function beforeUpdatePaymentMethod(AbstractEvent $event): void
    {
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $event->getModel();

        // We should set the conv fee for Payment Methods to 0 everytime Flywire gateway is enabled.
        if ($paymentMethod->dirty('gateway') && $paymentMethod->gateway == FlywireGateway::ID) {
            $paymentMethod->convenience_fee = 0;
        }
    }

    protected function getMerchantAccountValue(?int $id): ?int
    {
        if ($id) {
            return $id;
        }

        return $this->merchant_account_id;
    }

    protected function setMerchantAccountValue(?int $id): ?int
    {
        $this->merchant_account_id = $id;

        return $id;
    }

    public function setMerchantAccount(MerchantAccount $merchantAccount): void
    {
        $this->gateway = $merchantAccount->gateway;
        $this->merchant_account = $merchantAccount->id;
        $this->setRelation('merchant_account', $merchantAccount);
    }

    /**
     * Gets the name of this method.
     */
    public function toString(): string
    {
        $translator = TranslatorFacade::get();
        $locale = $translator->getLocale();
        if (!$locale) {
            $locale = $this->tenant()->getLocale();
        }

        $id = $this->id;
        $key = "payment_methods.$id";

        return $translator->trans($key, [], 'general', $locale);
    }

    /**
     * Checks if the payment method is enabled and matches the requirements.
     */
    public function enabled(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        /* Validate the payment method conditions are met */

        $method = $this->id;

        // Card payments only available to certain accounts
        $company = $this->tenant();
        if (self::CREDIT_CARD == $method && !$company->features->has('card_payments')) {
            return false;
        }

        // ACH is only available to certain accounts
        if (self::ACH == $method && !$company->features->has('ach')) {
            return false;
        }

        /* Validate the gateway conditions are met */

        // Offline payments require payment instructions
        $gateway = $this->gateway;
        if (!$gateway) {
            return null != $this->meta;
        }

        // Offline, mock, and test gateways do not require any setup
        if (in_array($gateway, [MockGateway::ID, TestGateway::ID])) {
            return true;
        }

        // PayPal gateway requires an email address
        if (PaymentGatewayMetadata::PAYPAL == $gateway) {
            return null != $this->meta;
        }

        // All other gateways require a connected merchant account
        $merchantAccount = MerchantAccount::queryWithTenant($this->tenant())
            ->where('gateway', $gateway)
            ->where('deleted', false)
            ->oneOrNull();

        return $merchantAccount instanceof MerchantAccount;
    }

    /**
     * Gets the merchant account.
     */
    public function merchantAccount(): ?MerchantAccount
    {
        return $this->relation('merchant_account');
    }

    /**
     * Gets the default merchant account for this payment method.
     *
     * @throws InvalidArgumentException when the gateway does not exist
     */
    public function getDefaultMerchantAccount(): MerchantAccount
    {
        // certain payment gateways do not have a merchant account
        if (!$this->gateway || in_array($this->gateway, [MockGateway::ID, PaymentGatewayMetadata::PAYPAL, TestGateway::ID])) {
            if (!isset($this->defaultMerchantAccount)) {
                $this->defaultMerchantAccount = new MerchantAccount();
                $this->defaultMerchantAccount->gateway = (string) $this->gateway;
            }

            return $this->defaultMerchantAccount;
        }

        $account = $this->merchantAccount();
        if (!$account) {
            throw new InvalidArgumentException("Merchant account does not exist for the `{$this->id}` payment method");
        }

        return $account;
    }

    /**
     * Checks if this payment method supports AutoPay.
     */
    public function supportsAutoPay(): bool
    {
        return in_array($this->id, [self::CREDIT_CARD, self::ACH, self::DIRECT_DEBIT]);
    }

    /**
     * Gets a payment method instance.
     */
    public static function instance(Company $company, string $methodId): PaymentMethod
    {
        $method = new self(['tenant_id' => $company->id(), 'id' => $methodId]);
        $method->setRelation('tenant_id', $company);

        return $method;
    }

    /**
     * Gets all of the enabled payment methods for the company.
     *
     * @return PaymentMethod[]
     */
    public static function allEnabled(Company $company): array
    {
        $result = [];
        $methods = self::queryWithTenant($company)
            ->where('enabled', true)
            ->all();

        foreach ($methods as $method) {
            if ($method->enabled()) {
                $result[$method->id] = $method;
            }
        }

        uasort($result, [self::class, 'sortMethods']);

        return $result;
    }

    /**
     * Checks if the company accepts payments through Invoiced.
     * This will return true if there is any payment method enabled
     * and properly configured.
     */
    public static function acceptsPayments(Company $company): bool
    {
        $methods = self::queryWithTenant($company)
            ->where('enabled', true)
            ->all();

        foreach ($methods as $method) {
            if ($method->enabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the company has AutoPay enabled.
     *
     * This will return true only if there is any AutoPay payment method enabled
     * and properly configured AND the company has the autopay feature flag.
     */
    public static function acceptsAutoPay(Company $company): bool
    {
        if (!$company->features->has('autopay')) {
            return false;
        }

        // check for a payment method that supports AutoPay
        $methods = PaymentMethod::allEnabled($company);
        foreach ($methods as $method) {
            if ($method->supportsAutoPay()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sorts payment methods according to their ordering.
     */
    public static function sortMethods(self $a, self $b): int
    {
        // check for custom ordering
        // a larger ordering here gives the payment method higher preference
        $aOrder = $a->order;
        $bOrder = $b->order;
        if (($aOrder > 0 || $bOrder > 0) && $aOrder != $bOrder) {
            return $aOrder < $bOrder ? 1 : -1;
        }

        // fallback to our default sorting
        // a lower ordering here gives the payment method higher preference
        $aOrder = array_search($a->toEnum(), self::METHODS);
        if (false === $aOrder) {
            $aOrder = PHP_INT_MAX;
        }

        $bOrder = array_search($b->toEnum(), self::METHODS);
        if (false === $bOrder) {
            $bOrder = PHP_INT_MAX;
        }

        return $aOrder > $bOrder ? 1 : -1;
    }

    /**
     * Returns the convenience fee expressed as a
     * fractional percent, e.g. 1.23%.
     */
    public function getConvenienceFeePercent(): float
    {
        return $this->convenience_fee / 100;
    }

    public function toEnum(): PaymentMethodType
    {
        return PaymentMethodType::fromString($this->id);
    }
}
