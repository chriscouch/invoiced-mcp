<?php

namespace App\AccountsReceivable\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\AccountsReceivable\Models\AppliedRate;
use App\AccountsReceivable\Models\Shipping;
use App\Core\Orm\Exception\ListenerException;

/**
 * @deprecated
 *
 * @property array $shipping
 */
trait HasShippingTrait
{
    protected array $_shipping;
    protected ?array $_saveShipping = null;

    /**
     * Gets the expanded shipping attached to this object.
     */
    public function shipping(bool $bustCache = false): array
    {
        if ($bustCache) {
            unset($this->_shipping);
        }

        return array_map(function ($shipping) {
            return $shipping instanceof Shipping ? $shipping->toArray() : $shipping;
        }, $this->shipping);
    }

    /**
     * Attaches the given shipping to this object (but does not save them).
     */
    public function setShipping(array $shipping): void
    {
        $this->_shipping = $shipping;
    }

    /**
     * Gets the attached shipping. The result could be an array,
     * if shipping were set but not saved yet, or else it could be
     * a collection of Shipping objects.
     */
    protected function getShippingValue(mixed $shipping): array
    {
        if (is_array($shipping)) {
            return $shipping;
        }

        if ($this->id() <= 0 && !isset($this->_shipping)) {
            return [];
        }

        // load the shipping from the database
        if (!isset($this->_shipping)) {
            // only get shipping on accounts with feature flag
            if ($this->tenant()->features->has('shipping')) {
                $k = ObjectType::fromModel($this)->typeName().'_id';
                $this->_shipping = Shipping::where($k, $this->id())
                    ->sort('order ASC,id ASC')
                    ->first(10);
            } else {
                $this->_shipping = [];
            }
        }

        return $this->_shipping;
    }

    /**
     * @throws ListenerException
     */
    protected function saveShipping(bool $isUpdate): void
    {
        $toSave = $this->_saveShipping;
        $this->_saveShipping = null;
        if (is_array($toSave)) {
            // only save shipping on accounts with feature flag
            if (count($toSave) > 0 && !$this->tenant()->features->has('shipping')) {
                throw new ListenerException('Account does not support shipping');
            }

            $shipping = AppliedRate::saveList($this, 'shipping', Shipping::class, $toSave, $isUpdate);
            $this->setShipping($shipping);
        }
    }
}
