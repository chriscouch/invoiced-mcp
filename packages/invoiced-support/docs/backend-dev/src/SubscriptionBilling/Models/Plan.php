<?php

namespace App\SubscriptionBilling\Models;

use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\PricingObject;
use App\Core\I18n\Currencies;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\PricingRules\TieredPricingRule;
use Doctrine\DBAL\Connection;

/**
 * @property string      $name
 * @property string      $currency
 * @property float|null  $amount
 * @property string      $interval
 * @property int         $interval_count
 * @property string      $description
 * @property string      $notes
 * @property string|null $catalog_item
 * @property int|null    $catalog_item_id
 * @property string      $pricing_mode
 * @property array|null  $tiers
 * @property int         $num_subscriptions
 */
class Plan extends PricingObject
{
    const LINE_ITEM_TYPE = 'plan';

    const QUANTITY_TYPE_CONSTANT = 'constant';
    const QUANTITY_TYPE_USAGE = 'usage';

    const PRICING_PER_UNIT = 'per_unit';
    const PRICING_VOLUME = 'volume';
    const PRICING_TIERED = 'tiered';
    const PRICING_CUSTOM = 'custom';

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                mutable: Property::MUTABLE_CREATE_ONLY,
                validate: ['callable', 'fn' => [self::class, 'validateAmount']],
            ),
            'interval' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['enum', 'choices' => ['day', 'week', 'month', 'year']],
            ),
            'interval_count' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'description' => new Property(),
            'notes' => new Property(
                null: true,
            ),
            'catalog_item' => new Property(
                null: true,
            ),
            'quantity_type' => new Property(
                validate: ['enum', 'choices' => ['constant', 'usage']],
                default: self::QUANTITY_TYPE_CONSTANT,
            ),
            'pricing_mode' => new Property(
                validate: ['enum', 'choices' => ['per_unit', 'tiered', 'volume', 'custom']],
                default: self::PRICING_PER_UNIT,
            ),
            'tiers' => new Property(
                type: Type::ARRAY,
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'validateTierArray']);
        self::saving([self::class, 'verifyAmount']);
        self::deleting(function (AbstractEvent $event): void {
            // cannot delete a plan if there are subscribers
            if ($event->getModel()->num_subscriptions > 0) {
                throw new ListenerException('Cannot delete plan with active subscribers');
            }
        });
        parent::initialize();
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['metadata'] = $this->metadata;

        return $result;
    }

    //
    // Hooks
    //

    public static function validateTierArray(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$tiers = $model->tiers) {
            return;
        }

        $rule = new TieredPricingRule();
        if (!$rule->validate($rule->serialize($tiers))) {
            throw new ListenerException($rule->getLastValidationError(), ['field' => 'tiers']);
        }
    }

    /**
     * Ensures amount property is properly configured based on pricing mode.
     */
    public static function verifyAmount(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if (self::PRICING_CUSTOM === $model->pricing_mode) {
            if (null !== $model->amount) {
                throw new ListenerException('Amounts are not allowed on plans that have a custom pricing mode', ['field' => 'amount']);
            }
        } elseif (null === $model->amount) {
            throw new ListenerException('Non-custom plans are required to have an amount', ['field' => 'amount']);
        }
    }

    //
    // Accessors
    //

    /**
     * Gets the number of active subscribers.
     */
    protected function getNumSubscriptionsValue(): int
    {
        /** @var Connection $connection */
        $connection = self::getDriver()->getConnection(null);

        return (int) $connection->fetchOne('SELECT COUNT(distinct s.id) FROM Subscriptions s LEFT JOIN SubscriptionAddons a ON a.subscription_id=s.id WHERE s.tenant_id=:tenantId AND (s.plan=:plan OR a.plan=:plan) AND s.canceled=0 AND s.finished=0', [
            'tenantId' => $this->tenant_id,
            'plan' => $this->id,
        ]);
    }

    //
    // Mutators
    //

    protected function setIntervalCountValue(int $intervalCount): int
    {
        // interval_count >= 1
        return (int) max(1, $intervalCount);
    }

    //
    // Validators
    //

    /**
     * Validates a plan amount.
     */
    public static function validateAmount(mixed $amount): bool
    {
        return null === $amount || (is_numeric($amount) && $amount >= 0);
    }

    //
    // Getters
    //

    /**
     * Gets the recurring interval for this plan.
     */
    public function interval(): Interval
    {
        return new Interval($this->interval_count, $this->interval);
    }

    /**
     * Gets the human-readable form of this plan's interval.
     */
    public function toString(): string
    {
        return (string) $this->interval();
    }

    /**
     * Gets the catalog item associated with this plan.
     */
    public function item(): ?Item
    {
        if ($itemId = $this->catalog_item) {
            return Item::getCurrent($itemId);
        }

        return null;
    }

    /**
     * Gets the plan name from the customer's perspective.
     */
    public function getCustomerFacingName(): string
    {
        if ($item = $this->item()) {
            return $item->name;
        }

        return $this->name;
    }

    /**
     * Generates the invoice line item for this plan.
     */
    public function lineItem(): array
    {
        $params = [
            'type' => self::LINE_ITEM_TYPE,
            'plan' => $this->id,
            'plan_id' => $this->internal_id,
            'name' => $this->name,
            'description' => $this->description,
            'unit_cost' => $this->amount,
        ];

        $params['metadata'] = $this->metadata;

        if ($item = $this->item()) {
            $params['name'] = $item->name;
            $params['catalog_item'] = $item->id;
            $params['catalog_item_id'] = $item->internal_id;

            if (!$params['description']) {
                $params['description'] = $item->description;
            }
        }

        return $params;
    }
}
