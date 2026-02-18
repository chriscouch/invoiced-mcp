<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentPlans\Models\PaymentPlanInstallment;

/**
 * @template T of ReceivableDocument
 *
 * @extends AbstractCsvExporter<T>
 */
abstract class DocumentCsvExporter extends AbstractCsvExporter
{
    private const DETAIL_LINE_ITEM = 'line_item';

    private const LINE_ITEM_COLUMNS = [
        'item',
        'description',
        'quantity',
        'unit_cost',
        'line_total',
        'discount',
        'tax',
    ];

    private bool $lineItemDetail;
    private int $unitCostPrecision = 2;

    abstract public function getClass(): string;

    /**
     * Gets the columns for a document summary portion of the export.
     */
    abstract protected function getColumnsDocument(): array;

    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get($this->getClass(), $this->company, $options);
        $listQueryBuilder->setSort('date ASC');

        $this->lineItemDetail = ($options['detail'] ?? '') == self::DETAIL_LINE_ITEM;

        if ($precision = $this->company->accounts_receivable_settings->unit_cost_precision) {
            $this->unitCostPrecision = $precision;
        }

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        $columns = [
            'customer.name',
            'customer.number',
            'customer.email',
            'customer.address1',
            'customer.address2',
            'customer.city',
            'customer.state',
            'customer.postal_code',
            'customer.country',
        ];
        $objectType = ObjectType::fromModelClass($this->getClass());
        $columns = array_merge(
            $columns,
            $this->getColumnsDocument(),
            $this->getMetadataColumns($objectType)
        );

        $metadataColumns = $this->getMetadataColumns(ObjectType::Customer);
        $columns = array_merge($columns, $metadataColumns);

        if ($this->lineItemDetail) {
            return array_merge($columns, self::LINE_ITEM_COLUMNS);
        }

        return $columns;
    }

    protected function isLineItemColumn(string $column): bool
    {
        return in_array($column, self::LINE_ITEM_COLUMNS);
    }

    protected function getCsvModelItems(Model $model): ?array
    {
        $lineItems = [];
        if ($this->lineItemDetail) {
            $lineItems = $model->items();

            // Build a row for each subtotal discount
            foreach ($model->discounts() as $discount) {
                $discount['_discount'] = true;
                $lineItems[] = $discount;
            }

            // Build a row for each subtotal tax
            foreach ($model->taxes() as $tax) {
                $tax['_tax'] = true;
                $lineItems[] = $tax;
            }
        }

        // Ensure that the invoice is always represented
        return $lineItems ?: null;
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        // customer
        if (str_starts_with($field, 'customer.') && $model instanceof ReceivableDocument) {
            $customer = $model->customer();
            $field = str_replace('customer.', '', $field);

            return $this->getCsvColumnValue($customer, $field);
        }

        return parent::getCsvColumnValue($model, $field);
    }

    /**
     * @param array|PaymentPlanInstallment $item
     */
    protected function getCsvLineItemColumnValue(Model $model, string $field, mixed $item): string
    {
        if (!$item || !is_array($item)) {
            return '';
        }

        if (isset($item['_discount'])) {
            return $this->getDiscountLineValue($item, $field);
        }

        if (isset($item['_tax'])) {
            return $this->getTaxLineValue($item, $field);
        }

        if ('discount' == $field) {
            // add together item-level discounts
            $itemDiscounts = Money::fromDecimal($model->currency, 0);
            foreach ($item['discounts'] as $rate) {
                $amount = Money::fromDecimal($model->currency, $rate['amount']);
                $itemDiscounts = $itemDiscounts->add($amount);
            }

            return $itemDiscounts->isPositive() ? (string) $itemDiscounts->toDecimal() : '';
        }

        if ('tax' == $field) {
            // add together item-level taxes
            $itemTaxes = Money::fromDecimal($model->currency, 0);
            foreach ($item['taxes'] as $rate) {
                $amount = Money::fromDecimal($model->currency, $rate['amount']);
                $itemTaxes = $itemTaxes->add($amount);
            }

            return $itemTaxes->isPositive() ? (string) $itemTaxes->toDecimal() : '';
        }

        return match ($field) {
            'item' => (string) $item['name'],
            'description' => (string) $item['description'],
            'quantity' => (string) $item['quantity'],
            'unit_cost' => (string) round($item['unit_cost'], $this->unitCostPrecision),
            'line_total' => (string) $item['amount'],
            default => '',
        };
    }

    private function getDiscountLineValue(array $item, string $field): string
    {
        return match ($field) {
            'item' => $item['coupon']['name'] ?? 'Discount',
            'discount' => (string) $item['amount'],
            default => '',
        };
    }

    private function getTaxLineValue(array $item, string $field): string
    {
        return match ($field) {
            'item' => $item['tax_rate']['name'] ?? 'Sales Tax',
            'tax' => (string) $item['amount'],
            default => '',
        };
    }
}
