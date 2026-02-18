<?php

namespace App\CashApplication\Models;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Libs\CashApplicationRulesEvaluator;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int      $id
 * @property string   $formula
 * @property int|null $customer
 * @property string   $method
 * @property bool     $ignore
 */
class CashApplicationRule extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'formula' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'customer' => new Property(
                type: Type::INTEGER,
                null: true,
                default: null,
                relation: Customer::class,
            ),
            'method' => new Property(
                type: Type::STRING,
                default: '',
            ),
            'ignore' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::saving([self::class, 'validateFormula']);
    }

    public static function validateFormula(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        (new CashApplicationRulesEvaluator())->validateRule($model->formula);
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $this->toArrayHook($result, [], [], []);

        return $result;
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        // customer name
        if (isset($include['customerName'])) {
            if ($this->customer()) {
                $result['customerName'] = $this->customer()->name;
            } else {
                $result['customerName'] = null;
            }
        }
    }

    /**
     * Gets the associated customer.
     */
    public function customer(): ?Customer
    {
        return $this->relation('customer');
    }
}
