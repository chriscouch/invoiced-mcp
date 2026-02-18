<?php

namespace App\AccountsReceivable\Traits;

use App\AccountsReceivable\Models\Customer;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * Added to models which require a customer.
 *
 * @property int $customer
 */
trait HasCustomerTrait
{
    public static function autoDefinitionCustomer(): array
    {
        return [
            'customer' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                relation: Customer::class,
            ),
        ];
    }

    /**
     * Verifies the customer relationship when creating.
     */
    public static function verifyCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        // handle customer objects
        $cid = $model->customer;
        if (is_array($cid)) { /* @phpstan-ignore-line */
            if (isset($cid['id'])) { /* @phpstan-ignore-line */
                // look up an existing customer
                $model->customer = $cid['id'];
            } else {
                // create a new customer
                $customer = new Customer();
                if (!$customer->create($cid)) {
                    throw new ListenerException('Could not create customer: '.$customer->getErrors(), ['field' => 'customer']);
                }
                $model->setCustomer($customer);

                return;
            }
        }

        if (!$cid) {
            throw new ListenerException('Customer missing', ['field' => 'customer']);
        }

        if (!$model->relation('customer')) {
            throw new ListenerException("No such customer: $cid", ['field' => 'customer']);
        }
    }

    /*
     * Ensures the customer is active.
     */
    public static function verifyActiveCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $customer = $model->customer();
        if ($customer instanceof Customer) {
            if (!$customer->active) {
                throw new ListenerException('This cannot be created because the customer is inactive', ['field' => 'customer']);
            }
        }
    }

    /**
     * Prevents the customer from being modified.
     */
    public static function protectCustomer(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->customer != $model->ignoreUnsaved()->customer) {
            throw new ListenerException('Invalid request parameter `customer`. The customer cannot be modified.', ['field' => 'customer']);
        }
    }

    /**
     * Sets the associated customer.
     */
    public function setCustomer(Customer $customer): void
    {
        $this->customer = (int) $customer->id();
        $this->setRelation('customer', $customer);
    }

    /**
     * Gets the associated customer.
     */
    public function customer(): Customer
    {
        return $this->relation('customer'); /* @phpstan-ignore-line */
    }
}
