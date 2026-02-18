<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\Enums\IntegrationType;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int             $id
 * @property IntegrationType $integration
 * @property bool            $read_customers
 * @property bool            $write_customers
 * @property bool            $read_invoices
 * @property bool            $read_invoices_as_drafts
 * @property bool            $read_pdfs
 * @property bool            $write_invoices
 * @property bool            $read_credit_notes
 * @property bool            $write_credit_notes
 * @property bool            $read_payments
 * @property bool            $write_payments
 * @property bool            $write_convenience_fees
 * @property array           $payment_accounts
 * @property int|null        $read_cursor
 * @property int|null        $invoice_start_date
 * @property int|null        $last_synced
 * @property object          $parameters
 */
class AccountingSyncProfile extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['id'];
    }

    protected static function getProperties(): array
    {
        return [
            'integration' => new Property(
                type: Type::ENUM,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
                enum_class: IntegrationType::class,
            ),
            'read_customers' => new Property(
                type: Type::BOOLEAN,
            ),
            'write_customers' => new Property(
                type: Type::BOOLEAN,
            ),
            'read_invoices' => new Property(
                type: Type::BOOLEAN,
            ),
            'read_invoices_as_drafts' => new Property(
                type: Type::BOOLEAN,
            ),
            'read_pdfs' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'write_invoices' => new Property(
                type: Type::BOOLEAN,
            ),
            'read_credit_notes' => new Property(
                type: Type::BOOLEAN,
            ),
            'write_credit_notes' => new Property(
                type: Type::BOOLEAN,
            ),
            'read_payments' => new Property(
                type: Type::BOOLEAN,
            ),
            'write_payments' => new Property(
                type: Type::BOOLEAN,
            ),
            'write_convenience_fees' => new Property(
                type: Type::BOOLEAN,
            ),
            'payment_accounts' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'read_cursor' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                in_array: false,
            ),
            'invoice_start_date' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'last_synced' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'parameters' => new Property(
                type: Type::OBJECT,
                default: [],
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([static::class, 'setStartDate']);
        self::saving([static::class, 'setReadCursor']);
    }

    public static function setStartDate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->invoice_start_date) {
            $model->invoice_start_date = CarbonImmutable::now()->getTimestamp();
        }
    }

    /**
     * Sets the read cursor value.
     */
    public static function setReadCursor(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->read_cursor) {
            $model->read_cursor = CarbonImmutable::now()->getTimestamp();
        }
    }

    public function getIntegrationType(): IntegrationType
    {
        return $this->integration;
    }

    /**
     * Gets the sync start date. This should be a date and not a date-time.
     * Date instances returned from this function should have the time set
     * to 00:00. The assumption when this function is called is that the
     * application has already switched to the company time zone.
     */
    public function getStartDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->invoice_start_date ?? 0)->setTime(0, 0);
    }
}
