<?php

namespace App\AccountsReceivable\Traits;

use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Utils\Enums\ObjectType;
use App\AccountsReceivable\Models\LineItem;
use App\Core\Orm\Exception\ListenerException;
use RuntimeException;

trait HasLineItemsTrait
{
    protected ?array $_saveLineItems = null;
    protected bool $_noItemSave = false;
    private ?array $_lineItems = null;

    //
    // Accessors
    //

    /**
     * Gets the attached line items. The result could be an array,
     * if line items were set but not saved yet, or else it could be
     * a collection of LineItem objects.
     */
    protected function getItemsValue(mixed $items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if ($this->id() <= 0 && null === $this->_lineItems) {
            return [];
        }

        // load the line items from the database
        if (null === $this->_lineItems) {
            $k = ObjectType::fromModel($this)->typeName().'_id';
            $this->_lineItems = LineItem::where($k, $this->id())
                ->sort('order ASC,id ASC')
                ->limit(1000)
                ->all()
                ->toArray();
        }

        return $this->_lineItems;
    }

    //
    // Getters
    //

    /**
     * Gets the expanded line items attached to this object.
     */
    public function items(bool $bustCache = false, bool $expandItem = false): array
    {
        if ($bustCache) {
            $this->_lineItems = null;
        }

        return array_map(function ($lineItem) use ($expandItem) {
            if (is_array($lineItem)) {
                return $lineItem;
            }

            if (!($lineItem instanceof LineItem)) {
                throw new RuntimeException('Invalid line item element');
            }

            $_line = $lineItem->toArray();
            if ($expandItem) {
                if ($item = $lineItem->item()) {
                    $_line['catalog_item'] = $item->toArray();
                } else {
                    $_line['catalog_item'] = null;
                }
            }

            return $_line;
        }, $this->items);
    }

    //
    // Setters
    //

    /**
     * Attaches the given line items to this object (but does not save them).
     *
     * @return $this
     */
    public function setLineItems(array $lineItems)
    {
        $this->_lineItems = $lineItems;

        return $this;
    }

    /**
     * Saves the given line items.
     *
     * @throws ListenerException
     */
    protected function saveLineItems(bool $isUpdate): void
    {
        if (null === $this->_saveLineItems) {
            return;
        }

        $order = 1;
        $lineItems = [];

        foreach ($this->_saveLineItems as $values) {
            try {
                $lineItem = $this->buildLineItemFromArray($values, $order);
            } catch (MultitenantException $e) {
                throw new ListenerException($e->getMessage(), ['field' => 'items']);
            }

            if (!$lineItem->noRecalculate()->save()) {
                throw new ListenerException('Could not save line items: '.$lineItem->getErrors(), ['field' => 'items']);
            }

            $lineItems[] = $lineItem;
            ++$order;
        }

        $this->_lineItems = $lineItems;
        $this->_saveLineItems = null;

        // when updating remove any line items that were not created
        if ($isUpdate) {
            $this->removeDeletedLineItems();
        }
    }

    /**
     * Builds a line item given an array of values.
     *
     * @throws MultitenantException when an applied rate is referenced by ID that does not exist
     */
    private function buildLineItemFromArray(array $values, int $order): LineItem
    {
        $lineItem = new LineItem();

        $id = array_value($values, 'id');
        $isPending = array_value($values, 'pending');
        if ($id && !$isPending) {
            // check if the line item already exists on this object
            $lineItem = null;
            foreach ($this->items as $_lineItem) {
                if ($_lineItem->id() == $id) {
                    $lineItem = $_lineItem;

                    break;
                }
            }
        } elseif ($id && $isPending) {
            // load the pending line item
            $lineItem = LineItem::where('id', $id)->oneOrNull();
            unset($values['pending']);
            unset($values['id']);
        }

        if (!$lineItem) {
            throw new MultitenantException("Referenced line item that does not exist: $id");
        }

        // set the properties on the line item
        foreach ($values as $k => $v) {
            $lineItem->$k = $v;
        }

        $lineItem->tenant_id = $this->tenant_id;
        $lineItem->order = $order;
        $lineItem->setParent($this);

        return $lineItem;
    }

    /**
     * Removes deleted line items.
     */
    private function removeDeletedLineItems(): void
    {
        $k = ObjectType::fromModel($this)->typeName().'_id';
        $query = self::getDriver()->getConnection(null)->createQueryBuilder()
            ->delete('LineItems')
            ->andWhere('tenant_id = '.$this->tenant_id)
            ->andWhere($k.' = '.$this->id());

        // shield saved line items from delete query
        if ($this->_lineItems) {
            $ids = [];
            foreach ($this->_lineItems as $lineItem) {
                $ids[] = $lineItem->id();
            }

            $query->andWhere('id NOT IN ('.implode(',', $ids).')');
        }

        $query->executeStatement();
    }
}
