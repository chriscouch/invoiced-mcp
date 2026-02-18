<?php

namespace App\SubscriptionBilling\Models;

use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\Tax;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventModelTrait;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;

class PendingLineItem extends LineItem implements EventObjectInterface
{
    use EventModelTrait;

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'populateFromCatalogItem'], 1);
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->where('customer_id IS NOT NULL');
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['customer'] = $this->customer_id;

        return $result;
    }

    public static function populateFromCatalogItem(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $currency = $model->tenant()->currency;

        // pull in details from catalog item
        if (isset($model->catalog_item) && $id = $model->catalog_item) {
            $catalogItem = Item::getCurrent($id);
            if (!$catalogItem) {
                throw new ListenerException('No such item: '.$id, ['field' => 'catalog_item']);
            }

            if ($catalogItem->currency) {
                $currency = $catalogItem->currency;
            }

            foreach ($catalogItem->lineItem() as $k => $v) {
                if (!$model->dirty($k)) {
                    $model->$k = $v;
                }
            }
        }

        // calculate taxes
        if (is_array($model->taxes)) {
            $taxes = Tax::expandList($model->taxes);
            $lineItemAmount = LineItem::calculateAmount($currency, $model->toArray());
            $changedLineItemAmount = false;
            foreach ($taxes as &$tax) {
                [$taxAmount, $taxMarkdown] = InvoiceCalculator::calculateTaxAmount($currency, $lineItemAmount->amount, $tax);
                $taxAmount = new Money($currency, $taxAmount);
                $tax['amount'] = $taxAmount->toDecimal();
                if ($taxMarkdown > 0) {
                    $changedLineItemAmount = true;
                    $lineItemAmount = $lineItemAmount->subtract(new Money($currency, $taxMarkdown));
                }
            }
            $model->taxes = $taxes;

            if ($changedLineItemAmount) {
                $model->_noRecalculate = true;
                $model->amount = $lineItemAmount->toDecimal();
            }
        }
    }

    /**
     * Sets the catalog_item value.
     * NOTE: this intentionally overrides the behavior of
     * the LineItem class to validate catalog items.
     */
    protected function setCatalogItemValue(mixed $id): ?string
    {
        return $id;
    }

    protected function getObjectValue(): string
    {
        return 'line_item';
    }

    public function getObjectName(): string
    {
        // Needed for metadata storage
        return 'line_item';
    }

    //
    // EventObjectInterface
    //

    public function getCreatedEventType(): ?EventType
    {
        return EventType::LineItemCreated;
    }

    public function getUpdatedEventType(): ?EventType
    {
        return EventType::LineItemUpdated;
    }

    public function getDeletedEventType(): ?EventType
    {
        return EventType::LineItemDeleted;
    }

    public function getEventObjectType(): ObjectType
    {
        return ObjectType::LineItem; // Needed for BC
    }

    public function getEventAssociations(): array
    {
        if (!$this->customer_id) {
            return [];
        }

        return [
            ['customer', $this->customer_id],
        ];
    }

    public function getEventObject(): array
    {
        $result = ModelNormalizer::toArray($this);
        $result['customer'] = $this->parent()?->toArray();

        return $result;
    }

    public function relation(string $name): Model|null
    {
        if ('customer' === $name) {
            $parent = $this->parent();
            if (!$parent) {
                return null;
            }
            if ($parent instanceof Customer) {
                return $parent;
            }

            return $parent->customer();
        }

        return parent::relation($name);
    }
}
