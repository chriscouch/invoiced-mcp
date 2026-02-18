<?php

namespace App\Integrations\QuickBooksOnline\Libs;

use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use stdClass;

/**
 * Maps data between QuickBooks and Invoiced.
 */
class QuickBooksMapper
{
    const SALES_LINE = 'SalesItemLineDetail';
    const DESCRIPTION_LINE = 'DescriptionOnly';
    const DISCOUNT_LINE = 'DiscountLineDetail';
    const GROUP_LINE = 'GroupLineDetail';
    const SUBTOTAL_LINE = 'SubTotalLineDetail';

    /**
     * Maps a QuickBooks custom field to a key on Invoiced.
     */
    public function buildMetadataKey(?QuickBooksOnlineSyncProfile $syncProfile, stdClass $quickbooksCustomField): string
    {
        $key = null;
        // Search for mapped custom field
        if ($syncProfile) {
            for ($i = 1; $i <= 3; ++$i) {
                $k = "custom_field_$i";
                $value = $syncProfile->$k;

                $parts = explode(':-:', (string) $value);
                if (3 !== count($parts)) {
                    continue;
                }

                [$metadataId, $definitionId, $name] = $parts;
                if (!$metadataId || !$definitionId || !$name) {
                    continue;
                }

                if ($definitionId == $quickbooksCustomField->DefinitionId) {
                    $key = $metadataId;
                    break;
                }
            }
        }

        if (!$key) {
            // Custom field not found in mapping, parse it into metadata key.
            $name = (string) $quickbooksCustomField->Name;
            $key = (string) preg_replace("/[^A-Za-z0-9 \-\_]/", '', $name);
            $key = strtolower(trim($key));
            $key = str_replace(['-', ' '], ['_', '_'], $key);
            $key = 'quickbooks_'.$key;
        }

        return $key;
    }

    /**
     * @throws TransformException
     */
    public function buildDocumentLines(QuickBooksApi $client, array $qboLines): array
    {
        $discount = 0;
        $items = [];
        foreach ($qboLines as $qboLine) {
            $lineType = (string) $qboLine->DetailType;
            if (self::SALES_LINE == $lineType) {
                $items[] = $this->buildLineItemSales($qboLine);
            } elseif (self::DESCRIPTION_LINE == $lineType) {
                $items[] = $this->buildLineItemDescription($qboLine);
            } elseif (self::DISCOUNT_LINE == $lineType) {
                $discount += (float) $qboLine->Amount;
            } elseif (self::GROUP_LINE == $lineType) {
                [$groupDiscount, $groupItems] = $this->buildLineItemsGroup($qboLine, $client);
                $items = array_merge($items, $groupItems);
                $discount += $groupDiscount;
            } elseif (self::SUBTOTAL_LINE == $lineType) {
                // do nothing because subtotal lines are derived
                // from other line items
                continue;
            } else {
                throw new TransformException('Unrecognized line item type: '.$lineType);
            }
        }

        return [$discount, $items];
    }

    /**
     * Builds an Invoiced line item from a QBO
     * sales line.
     */
    private function buildLineItemSales(stdClass $qboLine): array
    {
        $salesItem = $qboLine->SalesItemLineDetail;

        $item = [
            'name' => $salesItem->ItemRef->name ?? $salesItem->ItemRef->value,
            'description' => (string) ($qboLine->Description ?? null),
            'quantity' => (float) ($salesItem->Qty ?? 0),
            'unit_cost' => (float) ($salesItem->UnitPrice ?? null),
            'metadata' => [],
        ];

        // handle the scenario where the line item has a non-zero
        // amount but no quantity or unit cost
        $amount = (float) $qboLine->Amount;
        if ((!$item['quantity'] || !$item['unit_cost']) && $amount) {
            $item['quantity'] = 1;
            $item['unit_cost'] = $amount;
        }

        // service date
        if (property_exists($salesItem, 'ServiceDate')) {
            $item['metadata']['service_date'] = $salesItem->ServiceDate;
        }

        // class
        if (property_exists($salesItem, 'ClassRef')) {
            $item['metadata']['class'] = $salesItem->ClassRef->name;
        }

        // NOTE we can ignore tax because that is factored
        // in to the TotalTax property on the invoice

        // INVD-2775: QuickBooks gives the shipping line item
        // a weird name and we need to change it back.
        if ('SHIPPING_ITEM_ID' == $item['name']) {
            $item['name'] = 'Shipping';
        }

        return $item;
    }

    /**
     * Builds an Invoiced line item from a QBO
     * description line.
     */
    private function buildLineItemDescription(stdClass $qboLine): array
    {
        $item = [
            'name' => (string) ($qboLine->Description ?? null),
            'quantity' => 1,
            'unit_cost' => (float) ($qboLine->Amount ?? 0),
            'metadata' => [],
        ];

        // service date
        if (property_exists($qboLine->DescriptionLineDetail, 'ServiceDate')) {
            $item['metadata']['service_date'] = $qboLine->DescriptionLineDetail->ServiceDate;
        }

        // class
        if (property_exists($qboLine->DescriptionLineDetail, 'ClassRef')) {
            $item['metadata']['class'] = $qboLine->DescriptionLineDetail->ClassRef->name;
        }

        return $item;
    }

    /**
     * Builds an Invoiced line item from a QBO
     * group line.
     *
     * @throws TransformException
     */
    private function buildLineItemsGroup(stdClass $qboLine, QuickBooksApi $client): array
    {
        // look up the bundle and determine if we are supposed to display it
        // as a single line item or separate line items
        $itemId = (string) $qboLine->GroupLineDetail->GroupItemRef->value;

        try {
            $item = $client->getItem($itemId);
        } catch (IntegrationApiException $e) {
            throw new TransformException($e->getMessage(), 0, $e);
        }

        if ($item->PrintGroupedItems ?? false) {
            return $this->buildDocumentLines($client, $qboLine->GroupLineDetail->Line);
        }

        return [0, [$this->buildLineItemGroupSingle($qboLine)]];
    }

    private function buildLineItemGroupSingle(stdClass $qboLine): array
    {
        $groupItem = $qboLine->GroupLineDetail;

        $item = [
            'name' => $groupItem->GroupItemRef->name,
            'description' => (string) ($qboLine->Description ?? null),
            'quantity' => $groupItem->Quantity,
            'metadata' => [],
        ];

        // Derive the unit cost from the total line amount
        $amount = (float) $qboLine->Amount;
        if ($item['quantity'] > 0) {
            $item['unit_cost'] = $amount / $item['quantity'];
        } else {
            $item['quantity'] = 1;
            $item['unit_cost'] = $amount;
        }

        // service date
        if (property_exists($groupItem, 'ServiceDate')) {
            $item['metadata']['service_date'] = $groupItem->ServiceDate;
        }

        // obtain the class from the first sub-line because
        // class is not stored on the group line
        if (isset($qboLine->GroupLineDetail->Line)) {
            foreach ($qboLine->GroupLineDetail->Line as $subLine) {
                if (isset($subLine->SalesItemLineDetail)) {
                    if (property_exists($subLine->SalesItemLineDetail, 'ClassRef')) {
                        $item['metadata']['class'] = $subLine->SalesItemLineDetail->ClassRef->name;
                    }
                }
            }
        }

        return $item;
    }
}
