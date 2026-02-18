<?php

namespace App\AccountsReceivable\Models;

use App\Core\Authentication\Libs\UserContextFacade;
use App\Core\Authentication\Models\User;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;

/**
 * Represents a note attached to a customer or invoice.
 *
 * @property int          $id
 * @property int          $customer_id
 * @property Customer     $customer
 * @property int|null     $invoice_id
 * @property Invoice|null $invoice
 * @property int|null     $user_id
 * @property User|null    $user
 * @property string       $notes
 */
class Note extends AccountingWritableModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventModelTrait;

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                belongs_to: Customer::class,
            ),
            'invoice' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                belongs_to: Customer::class,
            ),
            'user' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
                belongs_to: User::class,
            ),
            'notes' => new Property(
                required: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'setUser']);
        self::creating([self::class, 'validateRelationships']);
    }

    /**
     * Sets the user ID when creating the note.
     */
    public static function setUser(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $user = UserContextFacade::get()->get();
        if (!$model->user_id && $user?->id > 0) {
            $model->user = $user;
        }
    }

    /**
     * Validates the relationships when creating the note.
     */
    public static function validateRelationships(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($invoice = $model->invoice) {
            $model->customer = $invoice->customer();
        } elseif (!$model->customer_id) {
            throw new ListenerException('Missing customer!', ['field' => 'customer']);
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['customer'] = $this->customer_id;
        $result['invoice'] = $this->invoice_id;

        if ($user = $this->user) {
            $result['user'] = $user->toArray();
        }

        return $result;
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $associations = [
            ['customer', $this->customer_id],
        ];
        if ($invoiceId = $this->invoice_id) {
            $associations[] = ['invoice', $invoiceId];
        }

        return $associations;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }

    public function isReconcilable(): bool
    {
        if ($this->skipReconciliation) {
            return false;
        }

        // we send data only for customer note
        return null == $this->invoice_id;
    }

    public function getAccountingObjectReference(): InvoicedObjectReference
    {
        return new InvoicedObjectReference($this->object, (string) $this->id(), $this->customer->name);
    }
}
