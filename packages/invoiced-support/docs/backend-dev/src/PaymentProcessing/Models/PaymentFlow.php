<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\CashApplication\Enums\PaymentItemIntType;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * @property int                    $id
 * @property string                 $identifier
 * @property PaymentFlowStatus      $status
 * @property string                 $currency
 * @property float                  $amount
 * @property Customer|null          $customer
 * @property PaymentMethodType|null $payment_method
 * @property ObjectType|null        $payment_source_type
 * @property int|null               $payment_source_id
 * @property bool                   $save_payment_source
 * @property bool                   $make_payment_source_default
 * @property string|null            $return_url
 * @property string|null            $email
 * @property PaymentFlowSource      $initiated_from
 * @property object                 $payment_values
 * @property PaymentLink|null       $payment_link
 * @property DateTimeInterface|null $processing_started_at
 * @property DateTimeInterface|null $completed_at
 * @property DateTimeInterface|null $canceled_at
 * @property string|null            $gateway
 * @property MerchantAccount|null   $merchant_account
 * @property string|null            $funding
 * @property string|null            $last4
 * @property int|null               $expMonth
 * @property int|null               $expYear
 * @property string|null            $country
 */
class PaymentFlow extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'identifier' => new Property(
                required: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: PaymentFlowStatus::class,
            ),
            'currency' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'payment_method' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: PaymentMethodType::class,
            ),
            'payment_source_type' => new Property(
                type: Type::ENUM,
                null: true,
                enum_class: ObjectType::class,
            ),
            'payment_source_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'save_payment_source' => new Property(
                type: Type::BOOLEAN,
            ),
            'make_payment_source_default' => new Property(
                type: Type::BOOLEAN,
            ),
            'return_url' => new Property(
                null: true,
            ),
            'email' => new Property(
                null: true,
            ),
            'initiated_from' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: PaymentFlowSource::class,
            ),
            'payment_values' => new Property(
                type: Type::OBJECT,
                default: [],
            ),
            'payment_link' => new Property(
                null: true,
                belongs_to: PaymentLink::class,
            ),
            'processing_started_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'completed_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'canceled_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'gateway' => new Property(
                null: true,
            ),
            'merchant_account' => new Property(
                null: true,
                belongs_to: MerchantAccount::class,
            ),
            'funding' => new Property(
                null: true,
            ),
            'last4' => new Property(
                null: true,
            ),
            'expMonth' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'expYear' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'country' => new Property(
                null: true,
            ),
        ];
    }


    protected function initialize(): void
    {
        self::updating([self::class, 'beforeUpdate']);

        parent::initialize();
    }

    public static function beforeUpdate(ModelUpdating $event): void
    {
        /** @var PaymentFlow $flow */
        $flow = $event->getModel();
        if ($flow->ignoreUnsaved()->completed_at) {
            throw new ListenerException("Failed to update completed payment flow");
        }

        $status = $flow->status;
        if (PaymentFlowStatus::Canceled == $status) {
            $flow->canceled_at = CarbonImmutable::now();

            return;
        }

        if (PaymentFlowStatus::Processing == $status) {
            $flow->processing_started_at = CarbonImmutable::now();

            return;
        }

        if (PaymentFlowStatus::Succeeded == $status || PaymentFlowStatus::Failed == $status) {
            $flow->completed_at = CarbonImmutable::now();
            if (!$flow->processing_started_at) {
                $flow->processing_started_at = CarbonImmutable::now();
            }
        }
    }

    public function setPaymentSource(PaymentSource $paymentSource): void
    {
        $this->payment_source_type = ObjectType::fromModel($paymentSource);
        $this->payment_source_id = $paymentSource->id;
    }

    public function applyConvenienceFee(array $convenienceFee): void
    {
        if (!$convenienceFee['amount']?->isPositive()) {
            return;
        }

        if ($this->getConvenienceFee()) {
            return;
        }

        $application = new PaymentFlowApplication();
        $application->type = PaymentItemIntType::ConvenienceFee;
        $application->amount = $convenienceFee['amount']->toDecimal();
        $application->payment_flow = $this;
        $application->saveOrFail();

        $amount = $this->getAmount();
        $this->amount = $amount->add($convenienceFee['amount'])->toDecimal();
        $this->saveOrFail();
    }

    public function getConvenienceFee(): ?PaymentFlowApplication
    {
        return PaymentFlowApplication::where('payment_flow_id', $this)
            ->where('type', PaymentItemIntType::ConvenienceFee->value)
            ->oneOrNull();
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    /**
     * @return PaymentFlowApplication[]
     */
    public function getApplications(): array
    {
        return PaymentFlowApplication::where('payment_flow_id', $this)
            ->all()
            ->toArray();
    }

    public function setBeforePayment(PaymentMethod $paymentMethod, ?PaymentSource $paymentSource, ?string $email, ?string $gateway): void
    {
        $this->payment_method = $paymentMethod->toEnum();
        $this->gateway = $paymentSource?->gateway ?? $gateway;
        if ($paymentSource) {
            $this->setPaymentSource($paymentSource);
        }
        if (!$this->email) {
            $this->email = $email;
        }
        try {
            $this->save();
        } catch (Throwable $e) {
            if (FlywireGateway::ID === $gateway ) {
                throw new FormException('Your payment was successfully processed but could not be saved. Please do not retry payment.');
            }
        }
    }

    public function setMerchantAccount(?MerchantAccount $merchantAccount): void
    {
        // no merchant account for these gateways
        if (in_array($this->gateway, [MockGateway::ID, PaymentGatewayMetadata::PAYPAL, TestGateway::ID])) {
            return;
        }

        $this->merchant_account = $merchantAccount;
        $this->save();
    }
}
