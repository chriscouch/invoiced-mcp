<?php

namespace App\Integrations\Intacct\Traits;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;

trait WriterTrait
{
    /**
     * Converts an Invoiced field mapping into a set of Intacct
     * custom fields.
     */
    protected function buildCustomFields(\stdClass $mapping, object $record): array
    {
        $customFields = [];
        foreach ((array) $mapping as $k => $v) {
            // recursively fetches the values based on the dot notation
            // metadata.key would result in this value lookup:
            // $record->metadata->key
            $propertyPath = explode('.', $k);
            $currentValue = $record;
            foreach ($propertyPath as $property) {
                // this protects against missing data or invalid configurations
                if (!is_object($currentValue)) {
                    $currentValue = null;

                    break;
                }

                // only set custom fields when there is a value present
                // metadata is a special case because isset() returns false
                if ('metadata' != $property) {
                    if ($currentValue instanceof Model && !$currentValue::definition()->has($property)) {
                        $currentValue = null;

                        break;
                    }

                    if (!$currentValue instanceof Model && !property_exists($currentValue, $property)) {
                        $currentValue = null;

                        break;
                    }
                }

                $currentValue = $currentValue->$property;
            }

            if ($currentValue) {
                $customFields[$v] = $currentValue;
            }
        }

        return $customFields;
    }

    /**
     * Gets the total tax applied to a document.
     */
    protected function getTotalTax(ReceivableDocument $document): Money
    {
        $totalTax = new Money($document->currency, 0);
        foreach ($document->taxes() as $tax) {
            $taxAmount = Money::fromDecimal($document->currency, $tax['amount']);
            $totalTax = $totalTax->add($taxAmount);
        }

        return $totalTax;
    }

    /**
     * Gets the total discount applied to a document.
     */
    protected function getTotalDiscount(ReceivableDocument $document): Money
    {
        $totalDiscount = new Money($document->currency, 0);
        foreach ($document->discounts() as $discount) {
            $discountAmount = Money::fromDecimal($document->currency, $discount['amount']);
            $totalDiscount = $totalDiscount->add($discountAmount);
        }

        return $totalDiscount;
    }
}
