<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Utils\Enums\ObjectType;
use App\SubscriptionBilling\Models\PendingLineItem;

/**
 * This represents the application of a rate to a document.
 * An example of a rate might be a discount or tax. Rates
 * can be applied to subtotals or line items.
 *
 * @property int         $id
 * @property int|null    $invoice_id
 * @property int|null    $line_item_id
 * @property int|null    $credit_note_id
 * @property int|null    $estimate_id
 * @property string      $type
 * @property float       $amount
 * @property string|null $rate
 * @property int|null    $rate_id
 * @property int         $order
 */
abstract class AppliedRate extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;
    const RATE_MODEL = self::class;

    protected static array $recalculateProperties = [
        'amount',
    ];

    private bool $_totalChanged = false;
    private LineItem|ReceivableDocument|null $_parent = null;
    private bool $_noRecalculate = false;
    private ?AbstractRate $_rate = null;

    //
    // Model Overrides
    //

    protected function initialize(): void
    {
        self::creating([static::class, 'setType']);
        self::created([self::class, 'recalculateParent']);
        self::deleting([self::class, 'preDelete']);
        self::deleted([self::class, 'recalculateParent']);

        self::updating([static::class, 'beforeUpdate'], -512);

        parent::initialize();
    }

    protected static function autoDefinitionAppliedRate(): array
    {
        return [
            'invoice_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'line_item_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'credit_note_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'estimate_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['discount', 'tax', 'shipping']],
                in_array: false,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
                validate: 'numeric',
            ),
            'rate' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
            ),
            'rate_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                in_array: false,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                required: true,
                validate: 'numeric',
                default: 1,
                in_array: false,
            ),
        ];
    }

    public function getTablename(): string
    {
        return 'AppliedRates';
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;

        // expand the rate object (if there is one)
        $rateModel = static::RATE_MODEL;
        if ($rate = $this->rate()) {
            $result['rate'] = $rate->toArray();
        } else {
            $result['rate'] = null;
        }

        // rename generic `rate` to type-specific property,
        // i.e. `coupon` or `tax_rate`
        $result[ObjectType::fromModelClass($rateModel)->typeName()] = $result['rate'];
        unset($result['rate']);

        unset($result['created_at']);

        return $result;
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->where('type', ObjectType::fromModelClass(static::class)->typeName());
    }

    //
    // Hooks
    //

    public static function setType(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->type = ObjectType::fromModelClass(static::class)->typeName();

        // handle passing in expanded rate objects by objects instead of ID
        // unless the rate / rate ID have already been set
        if (!$model->rate || !$model->rate_id) {
            $rateModel = static::RATE_MODEL;
            $rateType = ObjectType::fromModelClass($rateModel)->typeName();
            if (is_array($model->$rateType)) {
                $rateArray = $model->$rateType; // must be separate due to Model::__isset()
                $model->$rateType = $rateArray['id'] ?? null;
            }

            if ($model->$rateType) {
                $model->rate = $model->$rateType;
                unset($model->$rateType);
            }
        }

        $model->_totalChanged = true;
    }

    public static function beforeUpdate(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        $rateModel = static::RATE_MODEL;
        $rateType = ObjectType::fromModelClass($rateModel)->typeName();

        // handle passing in objects as types
        if ($model->dirty($rateType) && is_array($model->$rateType)) {
            $model->$rateType = $model->$rateType['id'];
        }

        if ($model->dirty($rateType)) {
            $model->rate = $model->$rateType;
            unset($model->$rateType);
        }

        // check to see if the invoice needs to be recalculated
        foreach (self::$recalculateProperties as $prop) {
            if ($model->dirty($prop)) {
                $model->_totalChanged = true;
            }
        }
    }

    /**
     * Called before a delete.
     */
    public static function preDelete(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->_totalChanged = true;
        $model->parent(); // cache the parent so it's available after delete
    }

    /**
     * Recalculates the parent when the total changes.
     */
    public static function recalculateParent(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->_totalChanged && !$model->_noRecalculate) {
            $parent = $model->parent();
            if ($parent && method_exists($parent, 'recalculate')) {
                $parent->recalculate();
            }
        }

        $model->_totalChanged = false;
        $model->_noRecalculate = false;
    }

    //
    // Mutators
    //

    /**
     * Sets the rate value.
     */
    protected function setRateValue(mixed $id): ?string
    {
        if (!$id || $id == $this->rate) {
            return $id;
        }

        // lock in a rate to the current version
        // by fetching the internal ID of the given rate
        $rateModel = static::RATE_MODEL;
        /** @var AbstractRate $rateModel */
        $rate = $rateModel::getCurrent($id);
        if (!$rate) {
            return null;
        }

        $this->_rate = $rate;
        $this->rate_id = $rate->internal_id;

        return $id;
    }

    //
    // Getters
    //

    /**
     * Gets the parent for this rate.
     */
    public function parent(): LineItem|ReceivableDocument|null
    {
        if ($this->_parent) {
            return $this->_parent;
        }

        if ($id = $this->invoice_id) {
            $this->_parent = new Invoice(['id' => $id]);
        } elseif ($id = $this->estimate_id) {
            $this->_parent = new Estimate(['id' => $id]);
        } elseif ($id = $this->credit_note_id) {
            $this->_parent = new CreditNote(['id' => $id]);
        } elseif ($id = $this->line_item_id) {
            $this->_parent = new LineItem(['id' => $id]);
        }

        return $this->_parent;
    }

    /**
     * Gets the rate for this line item.
     */
    public function rate(): ?AbstractRate
    {
        if (!$this->_rate && $id = $this->rate_id) {
            /** @var AbstractRate $rateModel */
            $rateModel = static::RATE_MODEL;
            $this->_rate = $rateModel::find($id);
        }

        return $this->_rate;
    }

    //
    // Setters
    //

    /**
     * The next save will not trigger a recalculation on the parent.
     *
     * @return $this
     */
    public function noRecalculate()
    {
        $this->_noRecalculate = true;

        return $this;
    }

    /**
     * Sets the applied rate parent.
     */
    public function setParent(Model $parent): void
    {
        $this->invoice_id = null;
        $this->line_item_id = null;
        $this->estimate_id = null;
        $this->credit_note_id = null;

        if ($parent instanceof PendingLineItem) {
            $k = 'line_item_id';
        } else {
            $k = ObjectType::fromModel($parent)->typeName().'_id';
        }
        $this->$k = $parent->id();

        $this->_parent = $parent;
    }

    //
    // Helpers
    //

    /**
     * Calculates the amount of a given applied rate array and
     * returns the zero-decimal amount.
     *
     * @param string $currency currency code amounts are in
     * @param int    $subtotal normalized amount to apply to
     */
    public static function calculateAmount(string $currency, int $subtotal, array $appliedRate): Money
    {
        $rateModel = static::RATE_MODEL;
        $rateType = ObjectType::fromModelClass($rateModel)->typeName();

        // check if there was a rate applied
        if (isset($appliedRate[$rateType]) && $appliedRate[$rateType] && 'AVATAX' != ($appliedRate[$rateType]['id'] ?? '')) {
            return $rateModel::applyRateToAmount($currency, $subtotal, $appliedRate[$rateType]);
        }

        if (isset($appliedRate['amount'])) {
            return Money::fromDecimal($currency, (float) $appliedRate['amount']);
        }

        return new Money($currency, 0);
    }

    /**
     * Expands a list of rate IDs into applied rates. The input
     * can be of the form:.
     *
     * i) [['amount' => null, 'coupon' => 'gst'], ['amount' => 100]]
     *    where each element is an applied rate object
     *
     * ii) ['gst', 'vat']
     *     where each element is the ID of a rate
     *
     * Any rate objects will be expanded when an ID is given
     *
     * @param array $rates list of applied rates
     */
    public static function expandList(array $rates): array
    {
        $list = [];
        $rateModel = static::RATE_MODEL;
        $rateType = ObjectType::fromModelClass($rateModel)->typeName();
        $usedIds = []; // prevent duplicates

        foreach ($rates as $appliedRate) {
            $rateId = false;
            // i) we have an Applied Rate
            if (is_array($appliedRate)) {
                // make sure the Rate exists and is expanded
                if (isset($appliedRate[$rateType]) && $appliedRate[$rateType]) {
                    $rateId = $appliedRate[$rateType];
                    if (is_array($rateId)) {
                        $rateId = array_value($rateId, 'id');
                    } else {
                        $rate = $rateModel::getCurrent($rateId);
                        if (!$rate) {
                            continue;
                        }
                        $appliedRate[$rateType] = $rate->toArray();
                    }
                }

                // ii) we have the ID of a Rate
                // use it to create an Applied Rate
            } else {
                $rateId = $appliedRate;
                $rate = $rateModel::getCurrent($rateId);
                if (!$rate) {
                    continue;
                }
                $appliedRate = [$rateType => $rate->toArray()];
            }

            // ensure that every applied rate has the `rate` and `amount` properties
            $appliedRate = array_replace(
                [$rateType => null],
                $appliedRate
            );

            // prevent usage of duplicate rates
            if ($rateId) {
                if (in_array($rateId, $usedIds)) {
                    continue;
                }
                $usedIds[] = $rateId;
            }

            $list[] = $appliedRate;
        }

        return $list;
    }

    /**
     * Compares two applied rates.
     *
     * @param array $a first field
     * @param array $b second field
     *
     * @return int 1: A is greater, 0: A = B, -1: B is greater
     */
    public static function compare(array $a, array $b): int
    {
        /*
            Order by scope:
              1. In Items
              2. In Subtotal

            Order by original order as a last resort
        */

        // order by scope if present
        $levelA = array_value($a, 'in_subtotal') - array_value($a, 'in_items');
        $levelB = array_value($b, 'in_subtotal') - array_value($b, 'in_items');

        if ($levelA != $levelB) {
            return ($levelA > $levelB) ? 1 : -1;
        }

        // if the elements have the same score, use the order if present
        return (isset($a['order']) && isset($b['order'])) ?
            $a['order'] - $b['order'] : 0;
    }

    /**
     * Saves applied rates of a single type.
     *
     * @throws ListenerException
     *
     * @return self[]
     */
    public static function saveList(MultitenantModel $model, string $type, string $appliedRateClass, array $toSave, bool $isUpdate): array
    {
        $order = 1;
        $appliedRates = [];
        $ids = [];

        $rateModel = $appliedRateClass::RATE_MODEL;
        $rateKey = ObjectType::fromModelClass($rateModel)->typeName();

        foreach ($toSave as $values) {
            // don't save $0 custom rates
            if (0 == $values['amount'] && !$values[$rateKey]) {
                continue;
            }

            try {
                $appliedRate = self::buildAppliedRateFromArray($model, $type, $appliedRateClass, $values, $order);
            } catch (MultitenantException $e) {
                throw new ListenerException($e->getMessage(), ['field' => $type]);
            }

            if (!$appliedRate->noRecalculate()->save()) {
                throw new ListenerException('Could not save applied rates: '.$appliedRate->getErrors(), ['field' => $type]);
            }

            $appliedRates[] = $appliedRate;
            $ids[] = $appliedRate->id();
            ++$order;
        }

        // when updating remove any applied rates that were not created
        if ($isUpdate) {
            self::removeDeletedAppliedRates($model, ObjectType::fromModelClass($appliedRateClass)->typeName(), $ids);
        }

        return $appliedRates;
    }

    /**
     * Builds an applied rate given an array of values.
     *
     * @throws MultitenantException when an applied rate is referenced by ID that does not exist
     */
    private static function buildAppliedRateFromArray(MultitenantModel $model, string $type, string $appliedRateClass, array $values, int $order): self
    {
        /** @var self $appliedRate */
        $appliedRate = new $appliedRateClass();
        if (isset($values['id'])) {
            // check if the applied rate already exists on this object
            $appliedRate = false;
            foreach ($model->$type as $_appliedRate) {
                if ($_appliedRate->id() == $values['id']) {
                    $appliedRate = $_appliedRate;

                    break;
                }
            }

            if (!$appliedRate) {
                $name = ObjectType::fromModelClass($appliedRateClass)->typeName();

                throw new MultitenantException("Referenced $name that does not exist: {$values['id']}");
            }
        }

        foreach ($values as $k => $v) {
            $appliedRate->$k = $v;
        }

        $appliedRate->tenant_id = $model->tenant_id;
        $appliedRate->setParent($model);
        $appliedRate->order = $order;

        return $appliedRate;
    }

    /**
     * Removes deleted applied rates.
     */
    private static function removeDeletedAppliedRates(MultitenantModel $model, string $type, array $idsToKeep): void
    {
        if ($model instanceof PendingLineItem) {
            $parentProperty = 'line_item_id';
        } else {
            $parentProperty = ObjectType::fromModel($model)->typeName().'_id';
        }
        $query = self::getDriver()->getConnection(null)->createQueryBuilder()
            ->delete('AppliedRates')
            ->andWhere('tenant_id = '.$model->tenant_id)
            ->andWhere("type = '$type'")
            ->andWhere($parentProperty.' = '.$model->id());

        // shield saved applied rates from delete query
        if (count($idsToKeep) > 0) {
            $query->andWhere('id NOT IN ('.implode(',', $idsToKeep).')');
        }

        $query->executeStatement();
    }
}
