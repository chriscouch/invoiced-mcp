<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Interfaces\LateFeeScheduleInterface;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                    $id
 * @property string                 $name
 * @property bool                   $enabled
 * @property DateTimeInterface      $start_date
 * @property DateTimeInterface|null $last_run
 * @property float                  $amount
 * @property bool                   $is_percent
 * @property int                    $grace_period
 * @property int                    $recurring_days
 * @property bool                   $default
 */
class LateFeeSchedule extends MultitenantModel implements LateFeeScheduleInterface
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                required: true,
                default: true,
            ),
            'start_date' => new Property(
                type: Type::DATE,
                required: true,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'is_percent' => new Property(
                type: Type::BOOLEAN,
            ),
            'grace_period' => new Property(
                type: Type::INTEGER,
                required: true,
                validate: ['range', 'min' => 0, 'max' => 365],
            ),
            'recurring_days' => new Property(
                type: Type::INTEGER,
                validate: ['range', 'min' => 0, 'max' => 365],
            ),
            'default' => new Property(
                type: Type::BOOLEAN,
            ),
            'last_run' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::deleting([self::class, 'checkCustomers']);
        self::saving([self::class, 'checkDefault']);

        parent::initialize();
    }

    public static function checkCustomers(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $customers = Customer::where('late_fee_schedule_id', $model)->count();

        if ($customers > 0) {
            throw new ListenerException('You can\'t delete late fee schedules with attached customers.');
        }
    }

    public static function checkDefault(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->default && LateFeeSchedule::where('id', $model->id(), '!=')
            ->where('default', true)->count()) {
            throw new ListenerException('You can have only one default late fee schedule.');
        }
    }

    /**
     * Gets the number of customers on this cadence.
     */
    protected function getNumCustomersValue(): int
    {
        return Customer::where('late_fee_schedule_id', $this->id)->count();
    }

    public function getGracePeriod(): int
    {
        return $this->grace_period;
    }

    public function getRecurringDays(): int
    {
        return $this->recurring_days;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function isPercent(): bool
    {
        return $this->is_percent;
    }
}
