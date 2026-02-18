<?php

namespace App\Integrations\AccountingSync\Traits;

use App\AccountsReceivable\Models\Item;
use App\Integrations\AccountingSync\Models\AccountingItemMapping;
use App\PaymentProcessing\Exceptions\ReconciliationException;

trait LineItemsMapperTrait
{
    /**
     * Decorate Line items with accounting ids.
     *
     * @throws ReconciliationException
     */
    private function decorateLineItems(): void
    {
        $fallbackId = $this->profile->parameters->fallback_item_id ?? null;
        if (!$fallbackId) {
            throw new ReconciliationException('No fallback item id set for the profile');
        }

        $items = $this->model->items(false, true);
        $catalogItems = [];
        // catalog item should be object for proper serialization
        foreach ($items as $key => $item) {
            if ($item['catalog_item']) {
                $items[$key]['catalog_item'] = (object) $item['catalog_item'];
                if (!property_exists($items[$key]['catalog_item'], 'accounting_id')) {
                    $catalogItems[] = $items[$key]['catalog_item']->id;
                }
                continue;
            }
            $items[$key]['catalog_item'] = (object) [
                'accounting_id' => $fallbackId,
            ];
        }

        $itemMappings = [];
        if ($catalogItems) {
            /** @var AccountingItemMapping[] $mappings */
            $mappings = AccountingItemMapping::join(Item::class, 'item_id', 'internal_id')
                ->with('item')
                ->where('integration_id', $this->profile->integration->value)
                ->where('CatalogItems.id', $catalogItems)
                ->all();
            foreach ($mappings as $mapping) {
                $itemMappings[$mapping->item->id] = $mapping->accounting_id;
            }
        }

        foreach ($items as $key => $item) {
            if (!property_exists($item['catalog_item'], 'accounting_id')) {
                $items[$key]['catalog_item']->accounting_id = $itemMappings[$item['catalog_item']->id] ?? $fallbackId;
            }
        }

        $this->model->setLineItems($items);
    }
}
