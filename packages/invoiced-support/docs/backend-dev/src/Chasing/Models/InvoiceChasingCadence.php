<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\InvoiceChasing\InvoiceChaseScheduleValidator;
use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use InvalidArgumentException;

/**
 * Global level invoice chasing cadence configuration.
 *
 * @property array  $chase_schedule
 * @property number $invoice_count
 * @property bool   $default
 */
class InvoiceChasingCadence extends AbstractChasingCadence
{
    // step actions
    const ON_ISSUE = 0;
    const BEFORE_DUE = 1;
    const AFTER_DUE = 2;
    const REPEATER = 3;
    const ABSOLUTE = 4;
    const AFTER_ISSUE = 5;

    private const COMPANY_LIMIT = 100;

    protected static function getProperties(): array
    {
        return [
            'chase_schedule' => new Property(
                type: Type::ARRAY,
            ),
            'default' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    public function initialize(): void
    {
        parent::initialize();
        self::saving([self::class, 'validateSchedule']);
        self::creating([self::class, 'companyLimit']);
        self::saving([self::class, 'checkDefault']);
    }

    //
    // Hooks
    //

    /**
     * @throws ListenerException
     */
    public static function validateSchedule(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->dirty('chase_schedule')) {
            return;
        }

        try {
            InvoiceChaseScheduleValidator::validate($model->chase_schedule);
        } catch (InvalidArgumentException $e) {
            throw new ListenerException('Invalid chase schedule: '.$e->getMessage());
        }
    }

    public static function companyLimit(): void
    {
        if (self::count() > self::COMPANY_LIMIT) {
            throw new ListenerException('You can not create more than '.self::COMPANY_LIMIT.' invoice chasing cadences per company.');
        }
    }

    /**
     * @throws ListenerException
     */
    public static function checkDefault(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->dirty('default') || !$model->default) {
            return;
        }

        // check for another default cadence
        $defaultCadences = InvoiceChasingCadence::where('default', true)->count();
        if ($defaultCadences > 0) {
            throw new ListenerException('A default cadence is already set.');
        }
    }

    public function getChaseSchedule(): InvoiceChaseSchedule
    {
        return InvoiceChaseSchedule::fromArrays($this->chase_schedule);
    }

    /**
     * Returns a count of invoices using this cadence.
     */
    public function getInvoiceCountValue(): int
    {
        return InvoiceDelivery::where('cadence_id', $this->id())->count();
    }
}
