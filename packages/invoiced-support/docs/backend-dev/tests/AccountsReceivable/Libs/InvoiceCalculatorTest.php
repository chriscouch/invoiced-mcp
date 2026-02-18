<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\ShippingRate;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\SalesTax\Models\TaxRate;
use App\Tests\AppTestCase;

class InvoiceCalculatorTest extends AppTestCase
{
    private static Coupon $coupon1;
    private static Coupon $coupon2;
    private static Coupon $coupon3;
    private static TaxRate $tax1;
    private static TaxRate $tax2;
    private static TaxRate $tax3;
    private static TaxRate $tax4;
    private static TaxRate $tax5;
    private static ShippingRate $shipping1;
    private static ShippingRate $shipping2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasItem();

        // create coupons
        self::$coupon1 = new Coupon();
        self::$coupon1->id = 'discount';
        self::$coupon1->name = 'Discount';
        self::$coupon1->value = 5;
        self::$coupon1->saveOrFail();

        self::$coupon2 = new Coupon();
        self::$coupon2->id = 'discount2';
        self::$coupon2->name = 'Discount';
        self::$coupon2->is_percent = false;
        self::$coupon2->value = 10;
        self::$coupon2->saveOrFail();

        self::$coupon3 = new Coupon();
        self::$coupon3->id = 'discount3';
        self::$coupon3->name = 'Discount';
        self::$coupon3->is_percent = false;
        self::$coupon3->value = 6;
        self::$coupon3->saveOrFail();

        // create taxes
        self::$tax1 = new TaxRate();
        self::$tax1->id = 'vat';
        self::$tax1->name = 'VAT';
        self::$tax1->value = 5;
        self::$tax1->saveOrFail();

        self::$tax2 = new TaxRate();
        self::$tax2->id = 'sales-tax';
        self::$tax2->name = 'Sales Tax';
        self::$tax2->value = 7;
        self::$tax2->saveOrFail();

        self::$tax3 = new TaxRate();
        self::$tax3->id = 'gst';
        self::$tax3->name = 'GST';
        self::$tax3->value = 2;
        self::$tax3->saveOrFail();

        self::$tax4 = new TaxRate();
        self::$tax4->id = 'qst';
        self::$tax4->name = 'QST';
        self::$tax4->value = 3;
        self::$tax4->saveOrFail();

        self::$tax5 = new TaxRate();
        self::$tax5->id = 'vat-inclusive';
        self::$tax5->name = 'VAT';
        self::$tax5->value = 20;
        self::$tax5->inclusive = true;
        self::$tax5->saveOrFail();

        // create shipping
        self::$shipping1 = new ShippingRate();
        self::$shipping1->id = 'shipping';
        self::$shipping1->name = 'Shipping';
        self::$shipping1->is_percent = false;
        self::$shipping1->value = 5.29;
        self::$shipping1->saveOrFail();

        self::$shipping2 = new ShippingRate();
        self::$shipping2->id = 'shipping2';
        self::$shipping2->name = 'Shipping';
        self::$shipping2->value = 6;
        self::$shipping2->saveOrFail();
    }

    public function testCalculateEmpty(): void
    {
        $invoice = InvoiceCalculator::calculate('usd', [], [], [], []);

        $this->assertTrue($invoice->calculated());
        $this->assertFalse($invoice->normalized());
        $this->assertEquals('usd', $invoice->currency);
        $this->assertEquals([], $invoice->items);
        $this->assertEquals([], $invoice->discounts);
        $this->assertEquals([], $invoice->taxes);
        $this->assertEquals([], $invoice->shipping);
        $this->assertEquals([
            'discounts' => [],
            'taxes' => [],
            'shipping' => [],
        ], $invoice->rates);
        $this->assertEquals(0, $invoice->subtotal);
        $this->assertEquals(0, $invoice->total);
    }

    public function testCalculate(): void
    {
        //
        // Setup
        //

        $items = [
            [
                'quantity' => 10,
                'description' => '',
                'unit_cost' => 100,
                'amount' => 1000,
                'discounts' => [
                    'discount3', // $6
                ],
                'taxes' => [
                    'gst', // 2%
                    'qst', // 3%
                ],
            ],
            [
                'quantity' => -1,
                'description' => 'test',
                'unit_cost' => 15.58068,
                'amount' => -15.58068,
            ],
            [
                'type' => 'product',
                'quantity' => 0,
                'unit_cost' => 0,
                'description' => '',
                'amount' => 0,
                'discounts' => [
                    'discount', // 5%
                    'discount2', // $10
                ],
            ],
            [
                'catalog_item' => 'test-item',
                'quantity' => 0,
            ],
            [
                'name' => 'No taxes or discounts',
                'quantity' => 2,
                'unit_cost' => 99,
                'discountable' => false,
                'taxable' => false,
            ],
        ];

        $discounts = [
            'discount', // 5%
            'discount2', // $10
            [
                'coupon' => null,
                'amount' => 10,
            ],
        ];

        $taxes = [
            'vat', // 5%
            'sales-tax', // 7%
            [
                'tax_rate' => null,
                'amount' => 5,
            ],
            [
                'tax_rate' => null,
                'amount' => 7.2,
            ],
        ];

        $shipping = [
            'shipping', // $5.29
            'shipping2', // 6%
            [
                'shipping_rate' => null,
                'amount' => 10,
            ],
        ];

        //
        // Calculate
        //

        $invoice = InvoiceCalculator::calculate('usd', $items, $discounts, $taxes, $shipping);

        //
        // Verify
        //

        $this->assertInstanceOf(CalculatedInvoice::class, $invoice);

        $this->assertTrue($invoice->calculated());
        $this->assertFalse($invoice->normalized());
        $this->assertEquals('usd', $invoice->currency);

        $subtotal = 1182.42;
        $excludedAmount = 198;
        $this->assertEquals($subtotal, $invoice->subtotal);

        $lineDiscounts = 6 + 10;
        $subtotalAfterLineDiscounts = $subtotal - $lineDiscounts;

        $lineTaxes = round((1000 - 6) * .02, 2);
        $lineTaxes += round((1000 - 6) * .03, 2);

        $discountableSubtotal = $subtotalAfterLineDiscounts - $excludedAmount;
        $discounts = round($discountableSubtotal * .05, 2);
        $discounts += 10 + 10;
        $discountedSubtotal = $subtotalAfterLineDiscounts - $discounts;

        $taxableSubtotal = $discountedSubtotal - $excludedAmount;
        $taxes = round($taxableSubtotal * .05, 2);
        $taxes += round($taxableSubtotal * .07, 2);
        $taxes += 5 + 7.2;

        $shipping = 5.29 + 10;
        $shipping += round($discountedSubtotal * .06, 2);

        $total = round($subtotal - $lineDiscounts + $lineTaxes - $discounts + $taxes + $shipping, 2);

        $this->assertEquals($total, $invoice->total);

        $expectedItems = [
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => 10,
                'unit_cost' => 100,
                'amount' => 1000,
                'discountable' => true,
                'discounts' => [
                    [
                        'coupon' => self::$coupon3->toArray(),
                        'amount' => 6,
                        '_calculated' => true,
                    ],
                ],
                'taxable' => true,
                'taxes' => [
                    [
                        'tax_rate' => self::$tax3->toArray(),
                        'amount' => 19.88,
                        '_calculated' => true,
                    ],
                    [
                        'tax_rate' => self::$tax4->toArray(),
                        'amount' => 29.82,
                        '_calculated' => true,
                    ],
                ],
            ],
            [
                'type' => null,
                'name' => '',
                'description' => 'test',
                'quantity' => -1,
                'unit_cost' => 15.58068,
                'amount' => -15.58,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
            ],
            [
                'type' => 'product',
                'name' => '',
                'description' => '',
                'quantity' => 0,
                'unit_cost' => 0,
                'amount' => 0,
                'discountable' => true,
                'discounts' => [
                    [
                        'coupon' => self::$coupon1->toArray(),
                        'amount' => 0,
                        '_calculated' => true,
                    ],
                    [
                        'coupon' => self::$coupon2->toArray(),
                        'amount' => 10,
                        '_calculated' => true,
                    ],
                ],
                'taxable' => true,
                'taxes' => [],
            ],
            [
                'type' => null,
                'catalog_item' => 'test-item',
                'catalog_item_id' => self::$item->internal_id,
                'name' => 'Test Item',
                'description' => 'Description',
                'quantity' => 0,
                'unit_cost' => 1000,
                'amount' => 0,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
            ],
            [
                'type' => null,
                'name' => 'No taxes or discounts',
                'description' => '',
                'quantity' => 2,
                'unit_cost' => 99,
                'amount' => 198,
                'discountable' => false,
                'discounts' => [],
                'taxable' => false,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expectedItems, $invoice->items);

        $expectedDiscounts = [
            [
                'coupon' => self::$coupon1->toArray(),
                'amount' => 48.42,
                '_calculated' => true,
            ],
            [
                'coupon' => self::$coupon2->toArray(),
                'amount' => 10,
                '_calculated' => true,
            ],
            [
                'coupon' => null,
                'amount' => 10,
                '_calculated' => true,
            ],
        ];
        $this->assertEquals($expectedDiscounts, $invoice->discounts);

        $expectedTaxes = [
            [
                'tax_rate' => self::$tax1->toArray(),
                'amount' => 45.00,
                '_calculated' => true,
            ],
            [
                'tax_rate' => self::$tax2->toArray(),
                'amount' => 63.00,
                '_calculated' => true,
            ],
            [
                'tax_rate' => null,
                'amount' => 5,
                '_calculated' => true,
            ],
            [
                'tax_rate' => null,
                'amount' => 7.2,
                '_calculated' => true,
            ],
        ];
        $this->assertEquals($expectedTaxes, $invoice->taxes);

        $exepctedShipping = [
            [
                'shipping_rate' => self::$shipping1->toArray(),
                'amount' => 5.29,
                '_calculated' => true,
            ],
            [
                'shipping_rate' => self::$shipping2->toArray(),
                'amount' => 65.88,
                '_calculated' => true,
            ],
            [
                'shipping_rate' => null,
                'amount' => 10,
                '_calculated' => true,
            ],
        ];
        $this->assertEquals($exepctedShipping, $invoice->shipping);

        $expectedRates = [
            'discounts' => [
                [
                    'coupon' => self::$coupon3->toArray(),
                    'in_items' => true,
                    'in_subtotal' => false,
                    'accumulated_total' => 6,
                ],
                [
                    'coupon' => self::$coupon1->toArray(),
                    'in_items' => true,
                    'in_subtotal' => true,
                    'accumulated_total' => 48.42,
                ],
                [
                    'coupon' => self::$coupon2->toArray(),
                    'accumulated_total' => 20,
                    'in_items' => true,
                    'in_subtotal' => true,
                ],
                [
                    'coupon' => null,
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 10,
                ],
            ],
            'taxes' => [
                [
                    'tax_rate' => self::$tax3->toArray(),
                    'in_items' => true,
                    'in_subtotal' => false,
                    'accumulated_total' => 19.88,
                ],
                [
                    'tax_rate' => self::$tax4->toArray(),
                    'in_items' => true,
                    'in_subtotal' => false,
                    'accumulated_total' => 29.82,
                ],
                [
                    'tax_rate' => self::$tax1->toArray(),
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 45.00,
                ],
                [
                    'tax_rate' => self::$tax2->toArray(),
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 63.00,
                ],
                [
                    'tax_rate' => null,
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 12.2,
                ],
            ],
            'shipping' => [
                [
                    'shipping_rate' => self::$shipping1->toArray(),
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 5.29,
                ],
                [
                    'shipping_rate' => self::$shipping2->toArray(),
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 65.88,
                ],
                [
                    'shipping_rate' => null,
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 10,
                ],
            ],
        ];
        $this->assertEquals($expectedRates, $invoice->rates);

        // normalize/denormalize should be idempotent
        $invoice->denormalize()->normalize()->normalize()->normalize();
        $this->assertTrue($invoice->normalized());
        $this->assertEquals($total * 100, $invoice->total);
    }

    public function testCalculateTaxInclusive(): void
    {
        //
        // Setup
        //

        $invoice = new CalculatedInvoice();
        $invoice->currency = 'usd';
        $invoice->items = [
            [
                'type' => null,
                'quantity' => 10,
                'name' => '',
                'description' => '',
                'unit_cost' => 100,
                'amount' => 1000,
                'discounts' => [],
                'taxes' => [],
                'taxable' => true,
                'discountable' => true,
            ],
            [
                'type' => null,
                'quantity' => -1,
                'name' => '',
                'description' => '',
                'unit_cost' => 15,
                'amount' => -15,
                'discounts' => [],
                'taxes' => [],
                'taxable' => true,
                'discountable' => true,
            ],
            [
                'type' => null,
                'quantity' => 1,
                'name' => '',
                'description' => '',
                'unit_cost' => 2000,
                'amount' => 2000,
                'discounts' => [],
                'taxes' => [],
                'taxable' => false,
                'discountable' => true,
            ],
        ];
        $invoice->taxes = [
            [
                'tax_rate' => self::$tax5->toArray(),
            ],
        ];

        //
        // Calculate
        //

        InvoiceCalculator::calculateInvoice($invoice);

        //
        // Verify
        //

        // denormalize and finalize the result
        $invoice->denormalize()->finalize();

        $this->assertTrue($invoice->calculated());
        $this->assertFalse($invoice->normalized());
        $this->assertEquals('usd', $invoice->currency);

        $this->assertEquals(2820.83, $invoice->subtotal);
        $this->assertEquals(2985, $invoice->total);

        $expectedItems = [
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => 10,
                'unit_cost' => 100,
                'amount' => 1000,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
            ],
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => -1,
                'unit_cost' => 15.0,
                'amount' => -15.0,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
            ],
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => 1,
                'unit_cost' => 2000,
                'amount' => 2000,
                'discountable' => true,
                'discounts' => [],
                'taxable' => false,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expectedItems, $invoice->items);

        $this->assertEquals([], $invoice->discounts);

        $expectedTaxes = [
            [
                'tax_rate' => self::$tax5->toArray(),
                'amount' => 164.17,
                '_calculated' => true,
            ],
        ];
        $this->assertEquals($expectedTaxes, $invoice->taxes);

        $this->assertEquals([], $invoice->shipping);

        $expectedRates = [
            'discounts' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$tax5->toArray(),
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 164.17,
                ],
            ],
            'shipping' => [],
        ];
        $this->assertEquals($expectedRates, $invoice->rates);
    }

    public function testCalculateTaxInclusiveLineItem(): void
    {
        //
        // Setup
        //

        $invoice = new CalculatedInvoice();
        $invoice->currency = 'usd';
        $invoice->items = [
            [
                'type' => null,
                'quantity' => 10,
                'name' => '',
                'description' => '',
                'unit_cost' => 100,
                'amount' => 1000,
                'discounts' => [],
                'taxes' => [
                    [
                        'tax_rate' => self::$tax5->toArray(),
                    ],
                ],
                'taxable' => true,
                'discountable' => true,
            ],
            [
                'type' => null,
                'quantity' => 1,
                'name' => '',
                'description' => '',
                'unit_cost' => 15,
                'amount' => 15,
                'discounts' => [],
                'taxes' => [
                    [
                        'tax_rate' => self::$tax4->toArray(),
                    ],
                ],
                'taxable' => true,
                'discountable' => true,
            ],
            [
                'type' => null,
                'quantity' => 1,
                'name' => '',
                'description' => '',
                'unit_cost' => 2000,
                'amount' => 2000,
                'discounts' => [],
                'taxes' => [],
                'taxable' => true,
                'discountable' => true,
            ],
        ];
        $invoice->discounts = [
            [
                'coupon' => self::$coupon1->toArray(),
            ],
        ];

        //
        // Calculate
        //

        InvoiceCalculator::calculateInvoice($invoice);

        //
        // Verify
        //

        // denormalize and finalize the result
        $invoice->denormalize()->finalize();

        $this->assertTrue($invoice->calculated());
        $this->assertFalse($invoice->normalized());
        $this->assertEquals('usd', $invoice->currency);

        $this->assertEquals(2848.33, $invoice->subtotal);
        $this->assertEquals(2873.03, $invoice->total);

        $expectedItems = [
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => 10,
                'unit_cost' => 100,
                'amount' => 833.33,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [
                    [
                        'tax_rate' => self::$tax5->toArray(),
                        '_calculated' => true,
                        'amount' => 166.67,
                    ],
                ],
            ],
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => 1,
                'unit_cost' => 15.0,
                'amount' => 15.0,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [
                    [
                        'tax_rate' => self::$tax4->toArray(),
                        '_calculated' => true,
                        'amount' => .45,
                    ],
                ],
            ],
            [
                'type' => null,
                'name' => '',
                'description' => '',
                'quantity' => 1,
                'unit_cost' => 2000,
                'amount' => 2000,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expectedItems, $invoice->items);

        $this->assertEquals([
            [
                'coupon' => self::$coupon1->toArray(),
                'amount' => 142.42,
                '_calculated' => true,
            ],
        ], $invoice->discounts);
        $this->assertEquals([], $invoice->taxes);
        $this->assertEquals([], $invoice->shipping);

        $expectedRates = [
            'discounts' => [
                [
                    'coupon' => self::$coupon1->toArray(),
                    'in_items' => false,
                    'in_subtotal' => true,
                    'accumulated_total' => 142.42,
                ],
            ],
            'taxes' => [
                [
                    'tax_rate' => self::$tax5->toArray(),
                    'in_items' => true,
                    'in_subtotal' => false,
                    'accumulated_total' => 166.67,
                ],
                [
                    'tax_rate' => self::$tax4->toArray(),
                    'in_items' => true,
                    'in_subtotal' => false,
                    'accumulated_total' => .45,
                ],
            ],
            'shipping' => [],
        ];
        $this->assertEquals($expectedRates, $invoice->rates);
    }
}
