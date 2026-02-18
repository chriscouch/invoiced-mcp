<?php

namespace App\PaymentProcessing\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;

/**
 * @property Level3LineItem[] $lineItems
 */
final readonly class Level3Data
{
    /**
     * @param Level3LineItem[] $lineItems
     */
    public function __construct(
        public string $poNumber,
        public CarbonImmutable $orderDate,
        public array $shipTo,
        public string $merchantPostalCode,
        public string $summaryCommodityCode,
        public Money $salesTax,
        public Money $shipping,
        public array $lineItems,
    ) {
    }

    public function toArray(): array
    {
        $lineItems = [];
        foreach ($this->lineItems as $lineItem) {
            $lineItems[] = [
                'product_code' => $lineItem->productCode,
                'description' => $lineItem->description,
                'commodity_code' => $lineItem->commodityCode,
                'quantity' => $lineItem->quantity,
                'unit_cost' => $lineItem->unitCost->toDecimal(),
                'unit_of_measure' => $lineItem->unitOfMeasure,
                'discount' => $lineItem->discount->toDecimal(),
            ];
        }

        return [
            'po_number' => $this->poNumber,
            'order_date' => $this->orderDate->format('Y-m-d'),
            'ship_to' => $this->shipTo,
            'merchant_postal_code' => $this->merchantPostalCode,
            'summary_commodity_code' => $this->summaryCommodityCode,
            'line_items' => $lineItems,
            'tax' => $this->salesTax->toDecimal(),
            'shipping' => $this->shipping->toDecimal(),
        ];
    }
}
