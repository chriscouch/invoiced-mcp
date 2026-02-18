<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\Item;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Traits\AccountingLoaderTrait;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingItem;

class AccountingItemLoader implements LoaderInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    use AccountingLoaderTrait;

    /**
     * @param AccountingItem $record
     */
    public function load(AbstractAccountingRecord $record): ImportRecordResult
    {
        $item = Item::where('name', $record->values['name'])
            ->where('archived', false)
            ->oneOrNull();
        if ($item instanceof Item) {
            // If the unit cost differs, archive the existing
            // item and create a new one. Otherwise, update
            // the description on the existing item.
            if ($record->values['unit_cost'] != $item->unit_cost) {
                // archive the old item
                if (!$item->delete()) {
                    throw $this->makeException($record, 'Could not delete item: '.$item->getErrors());
                }

                // create new item
                $item = $this->createItem($record->values);
                if (!$item->save()) {
                    throw $this->makeException($record, 'Could not update item: '.$item->getErrors());
                }

                return $this->makeUpdateResult($record, $item);
            }

            $item->description = $record->values['description'] ?? null;
            if (!$item->save()) {
                throw $this->makeException($record, 'Could not create item: '.$item->getErrors());
            }

            return $this->makeUpdateResult($record, $item);
        }

        $item = $this->createItem($record->values);
        if (!$item->save()) {
            throw $this->makeException($record, 'Could not update item: '.$item->getErrors());
        }

        return $this->makeCreateResult($record, $item);
    }

    /**
     * Creates an item from an array of values.
     */
    private function createItem(array $record): Item
    {
        $item = new Item();
        $item->name = $record['name'];
        $item->description = $record['description'] ?? null;
        $item->unit_cost = $record['unit_cost'];

        return $item;
    }
}
