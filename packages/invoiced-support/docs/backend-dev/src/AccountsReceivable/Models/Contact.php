<?php

namespace App\AccountsReceivable\Models;

use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Countries;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\Search\Traits\SearchableTrait;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Sending\Email\Interfaces\IsEmailParticipantInterface;
use App\Sending\Email\Traits\IsEmailParticipantTrait;

/**
 * @property int              $id
 * @property int              $customer_id
 * @property Customer         $customer
 * @property string           $name
 * @property string|null      $email
 * @property bool             $primary
 * @property string|null      $title
 * @property string|null      $department
 * @property string|null      $phone
 * @property bool             $sms_enabled
 * @property string|null      $address1
 * @property string|null      $address2
 * @property string|null      $city
 * @property string|null      $state
 * @property string|null      $postal_code
 * @property string|null      $country
 * @property bool             $send_new_invoices
 * @property int|null         $role_id
 * @property ContactRole|null $role
 * @property string           $address
 */
class Contact extends MultitenantModel implements EventObjectInterface, IsEmailParticipantInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;
    use IsEmailParticipantTrait;
    use SearchableTrait;

    private const MAX_CONTACTS_PER_CUSTOMER = 100;

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Customer::class,
            ),
            'name' => new Property(
                required: true,
                validate: ['string', 'min' => 1, 'max' => 255],
            ),
            'email' => new Property(
                null: true,
                validate: 'email',
            ),
            'primary' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'title' => new Property(
                null: true,
            ),
            'department' => new Property(
                null: true,
            ),
            'phone' => new Property(
                null: true,
            ),
            'sms_enabled' => new Property(
                type: Type::BOOLEAN,
            ),

            /* Address */

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
            'send_new_invoices' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'role' => new Property(
                null: true,
                belongs_to: ContactRole::class,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'verifyCustomer']);
        self::creating([self::class, 'inheritFromCustomer']);

        parent::initialize();
    }

    /**
     * Verifies the customer relationship when creating.
     */
    public static function verifyCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $customerId = $model->customer_id;
        if (!$customerId) {
            throw new ListenerException('Customer missing', ['field' => 'customer']);
        }

        $count = self::where('customer_id', $customerId)->count();
        if ($count >= self::MAX_CONTACTS_PER_CUSTOMER) {
            throw new ListenerException('The maximum number of contacts ('.self::MAX_CONTACTS_PER_CUSTOMER.') per customer has been reached.');
        }
    }

    /**
     * Inherits values from the parent customer, when available.
     */
    public static function inheritFromCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // country
        if (!$model->country) {
            $model->country = $model->customer->country;
        }
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

        // cannot generate an address if we do not know the country
        if (!$this->country) {
            return '';
        }

        // only show the country line when the customer and
        // company are in different countries
        $showCountry = $this->country != $this->tenant()->country;
        $options = [
            'showCountry' => $showCountry,
            'showName' => false,
        ];

        $af = new AddressFormatter();

        return $af->setFrom($this)->format($options);
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
