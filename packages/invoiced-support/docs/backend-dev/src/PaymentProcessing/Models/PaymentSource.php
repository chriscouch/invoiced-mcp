<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\Traits\EventModelTrait;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use ICanBoogie\Inflector;

/**
 * This model is a reference to a payment source stored
 * on a merchant's payment gateway account. It should be extended
 * by the various payment sources we support, like credit card or bank accounts.
 *
 * @property int         $id
 * @property int         $customer_id
 * @property Customer    $customer
 * @property string      $gateway
 * @property string|null $gateway_id
 * @property string|null $gateway_customer
 * @property string|null $gateway_setup_intent
 * @property int|null    $merchant_account
 * @property int|null    $merchant_account_id
 * @property bool        $chargeable
 * @property string|null $failure_reason
 * @property string|null $receipt_email
 */
abstract class PaymentSource extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;

    protected static function autoDefinitionPaymentSource(): array
    {
        return [
            'customer' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: Customer::class,
            ),
            'gateway' => new Property(),
            'gateway_id' => new Property(
                null: true,
            ),
            'gateway_customer' => new Property(
                null: true,
            ),
            'gateway_setup_intent' => new Property(
                null: true,
            ),
            'merchant_account_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'merchant_account' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                relation: MerchantAccount::class,
            ),
            'chargeable' => new Property(
                type: Type::BOOLEAN,
            ),
            'failure_reason' => new Property(
                null: true,
            ),
            'receipt_email' => new Property(
                type: Type::STRING,
                null: true,
            ),
        ];
    }

    //
    // Getters
    //

    /**
     * Gets the payment method for this source.
     */
    abstract public function getMethod(): string;

    /**
     * Gets the name of this type of payment source.
     */
    public function getTypeName(): string
    {
        $inflector = Inflector::get();

        return $inflector->titleize($inflector->underscore(static::modelName()));
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

    /**
     * Converts the payment source to a string.
     *
     * @param bool $short returns the abbreviated version
     */
    abstract public function toString(bool $short = false): string;

    /**
     * Checks if the source needs to be verified.
     */
    abstract public function needsVerification(): bool;

    /**
     * Gets the payment method associated with this
     * payment source.
     */
    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::instance($this->tenant(), $this->getMethod());
    }

    /**
     * Gets the merchant account associated with this
     * payment source.
     */
    public function getMerchantAccount(): MerchantAccount
    {
        $account = $this->relation('merchant_account');

        if (!$account) {
            $account = new MerchantAccount();
            $account->gateway = $this->gateway;
        }

        return $account;
    }

    public function supportsConvenienceFees(): bool
    {
        return false;
    }

    public function isDefault(): bool
    {
        $customer = $this->customer;

        return $customer->default_source_type == ObjectType::fromModel($this)->typeName() &&
               $customer->default_source_id == $this->id();
    }

    /**
     * Removes the payment source from the payment gateway and
     * from the customer.
     * Overrides the delete function by not actually deleting this
     * source. This will remove the source as the default and
     * it will unmark it as chargeable.
     *
     * @throws PaymentSourceException when the source cannot be deleted from the payment gateway
     */
    public function delete(): bool
    {
        // clear it as the customer's default source
        if ($this->isDefault()) {
            $this->customer->clearDefaultPaymentSource();
        }

        // unmark as chargeable
        EventSpool::disablePush();
        $this->chargeable = false;
        $this->save();
        EventSpool::enablePop();

        // create a payment_source.deleted event
        $metadata = $this->getEventObject();
        $associations = $this->getEventAssociations();

        $pendingEvent = new PendingDeleteEvent($this, EventType::PaymentSourceDeleted, $metadata, $associations);
        EventSpoolFacade::get()->enqueue($pendingEvent);

        return true;
    }

    //
    // EventModelTrait
    //

    /**
     * Gets the event name for a create event.
     */
    public function getCreatedEventType(): ?EventType
    {
        // do not create events for one-time charges
        if (!$this->chargeable) {
            return null;
        }

        return EventType::PaymentSourceCreated;
    }

    /**
     * Gets the event name for an update event.
     */
    public function getUpdatedEventType(): ?EventType
    {
        return EventType::PaymentSourceUpdated;
    }

    /**
     * Gets the event name for a delete event.
     */
    public function getDeletedEventType(): ?EventType
    {
        return EventType::PaymentSourceDeleted;
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        return [
            ['customer', $this->customer_id],
        ];
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }
}
