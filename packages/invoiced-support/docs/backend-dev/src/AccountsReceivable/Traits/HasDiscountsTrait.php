<?php

namespace App\AccountsReceivable\Traits;

use App\AccountsReceivable\Models\AppliedRate;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Discount;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Utils\Enums\ObjectType;
use App\SubscriptionBilling\Models\PendingLineItem;

/**
 * @property array $discounts
 */
trait HasDiscountsTrait
{
    protected array $_discounts;
    protected ?array $_saveDiscounts = null;

    /**
     * Gets the expanded discounts attached to this object.
     */
    public function discounts(bool $bustCache = false): array
    {
        if ($bustCache) {
            unset($this->_discounts);
        }

        return array_map(function ($discount) {
            return $discount instanceof Discount ? $discount->toArray() : $discount;
        }, $this->discounts);
    }

    /**
     * Attaches the given discounts to this object (but does not save them).
     */
    public function setDiscounts(array $discounts): void
    {
        $this->_discounts = $discounts;
    }

    /**
     * Gets the attached discounts. The result could be an array,
     * if discounts were set but not saved yet, or else it could be
     * a collection of Discount objects.
     */
    protected function getDiscountsValue(mixed $discounts): array
    {
        if (is_array($discounts)) {
            return $discounts;
        }

        if ($this->id() <= 0 && !isset($this->_discounts)) {
            return [];
        }

        // load the discounts from the database
        if (!isset($this->_discounts)) {
            if ($this instanceof PendingLineItem) {
                $k = 'line_item_id';
            } else {
                $k = ObjectType::fromModel($this)->typeName().'_id';
            }
            $this->_discounts = Discount::where($k, $this->id())
                ->sort('order ASC,id ASC')
                ->first(10);
        }

        return $this->_discounts;
    }

    /**
     * @throws ListenerException
     */
    protected function saveDiscounts(bool $isUpdate): void
    {
        $toSave = $this->_saveDiscounts;
        $this->_saveDiscounts = null;
        if (is_array($toSave)) {
            $discounts = AppliedRate::saveList($this, 'discounts', Discount::class, $toSave, $isUpdate);
            $this->setDiscounts($discounts);
        }
    }

    public function setDiscountRate(array $discounts): void
    {
        $this->discounts = array_map(function ($discount) {
            if (!isset($discount['coupon']) || !$discount['coupon']) {
                return $discount;
            }
            if (isset($discount['coupon']['value']) && isset($discount['coupon']['is_percent'])) {
                return $discount;
            }
            if (!isset($discount['coupon']['id'])) {
                return $discount;
            }
            $discount['coupon'] = Coupon::where('id', $discount['coupon']['id'])->oneOrNull()?->toArray();

            return $discount;
        }, $discounts);
    }
}
