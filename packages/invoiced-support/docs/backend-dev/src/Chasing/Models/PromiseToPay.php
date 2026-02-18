<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\Traits\EventObjectTrait;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * This model keeps track of when an invoice payment is expected.
 *
 * @property int         $id
 * @property Customer    $customer
 * @property int         $customer_id
 * @property Invoice     $invoice
 * @property int         $invoice_id
 * @property string|null $method
 * @property string|null $reference
 * @property int|null    $date
 * @property string      $currency
 * @property float       $amount
 * @property bool        $broken
 * @property bool        $kept
 */
class PromiseToPay extends MultitenantModel implements EventObjectInterface
{
    use AutoTimestamps;
    use EventObjectTrait;

    private ?EventType $event = null;

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Customer::class,
            ),
            'invoice' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                belongs_to: Invoice::class,
            ),
            'method' => new Property(
                null: true,
            ),
            'reference' => new Property(
                null: true,
            ),
            'date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                validate: 'timestamp',
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                validate: ['range', 'min' => 0],
            ),
            'kept' => new Property(
                type: Type::BOOLEAN,
            ),
            'broken' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    public function getTablename(): string
    {
        return 'ExpectedPaymentDates';
    }

    protected function initialize(): void
    {
        self::saving([self::class, 'writingEvent']);
        self::saved([self::class, 'wroteEvent']);

        parent::initialize();
    }

    public function setEvent(EventType $event): void
    {
        $this->event = $event;
    }

    public function getEvent(): ?EventType
    {
        return $this->event;
    }

    public static function writingEvent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->dirty('broken') && $model->broken) {
            $model->setEvent(EventType::PromiseToPayBroken);
        } elseif ($model->dirty('date') && $model->date) {
            $model->setEvent(EventType::InvoicePaymentExpected);
        }
    }

    /**
     * Writes an event when it's being created.
     */
    public static function wroteEvent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (!$event = $model->getEvent()) {
            return;
        }

        $pendingEvent = new PendingEvent(
            object: $model,
            type: $event
        );
        EventSpoolFacade::get()->enqueue($pendingEvent);
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        return [
            ['customer', $this->customer_id],
            ['invoice', $this->invoice_id],
        ];
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer', 'invoice']);
    }
}
