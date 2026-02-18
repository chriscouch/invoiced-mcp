<?php

namespace App\AccountsReceivable\Traits;

use App\AccountsReceivable\Models\AppliedRate;
use App\AccountsReceivable\Models\Tax;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Utils\Enums\ObjectType;
use App\SalesTax\Models\TaxRate;
use App\SubscriptionBilling\Models\PendingLineItem;

/**
 * @property array $taxes
 */
trait HasTaxesTrait
{
    protected array $_taxes;
    protected ?array $_saveTaxes = null;

    /**
     * Gets the expanded taxes attached to this object.
     */
    public function taxes(bool $bustCache = false): array
    {
        if ($bustCache) {
            unset($this->_taxes);
        }

        return array_map(function ($tax) {
            return $tax instanceof Tax ? $tax->toArray() : $tax;
        }, $this->taxes);
    }

    /**
     * Attaches the given taxes to this object (but does not save them).
     */
    public function setTaxes(array $taxes): void
    {
        $this->_taxes = $taxes;
    }

    /**
     * Gets the attached taxes. The result could be an array,
     * if taxes were set but not saved yet, or else it could be
     * a collection of Tax objects.
     */
    protected function getTaxesValue(mixed $taxes): array
    {
        if (is_array($taxes)) {
            return $taxes;
        }

        if ($this->id() <= 0 && !isset($this->_taxes)) {
            return [];
        }

        // load the taxes from the database
        if (!isset($this->_taxes)) {
            if ($this instanceof PendingLineItem) {
                $k = 'line_item_id';
            } else {
                $k = ObjectType::fromModel($this)->typeName().'_id';
            }
            $this->_taxes = Tax::where($k, $this->id())
                ->sort('order ASC,id ASC')
                ->first(10);
        }

        return $this->_taxes;
    }

    /**
     * @throws ListenerException
     */
    protected function saveTaxes(bool $isUpdate): void
    {
        $toSave = $this->_saveTaxes;
        $this->_saveTaxes = null;
        if (is_array($toSave)) {
            $taxes = AppliedRate::saveList($this, 'taxes', Tax::class, $toSave, $isUpdate);
            $this->setTaxes($taxes);
        }
    }

    public function setTaxRate(array $taxes): void
    {
        $this->taxes = array_map(function ($tax) {
            if (!isset($tax['tax_rate']) || !$tax['tax_rate']) {
                return $tax;
            }
            if (isset($tax['tax_rate']['value']) && isset($tax['tax_rate']['is_percent'])) {
                return $tax;
            }
            if (!isset($tax['tax_rate']['id'])) {
                return $tax;
            }
            $tax['tax_rate'] = TaxRate::where('id', $tax['tax_rate']['id'])->oneOrNull()?->toArray();

            return $tax;
        }, $taxes);
    }
}
