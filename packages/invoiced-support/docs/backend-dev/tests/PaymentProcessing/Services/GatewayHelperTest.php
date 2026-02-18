<?php

namespace App\Tests\PaymentProcessing\Services;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Tax;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\ValueObjects\Level3Data;
use App\SalesTax\Models\TaxRate;
use App\Tests\AppTestCase;

class GatewayHelperTest extends AppTestCase
{
    private static Tax $tax;
    private static Invoice $invoice2;
    private static Invoice $invoice3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTaxRate();
        self::hasItem();

        self::$tax = new Tax();
        self::$tax->tenant_id = (int) self::$company->id();
        self::$tax->amount = 15;
        self::$tax->rate = 'tax_rate';

        self::$invoice->total = 100.00;
        self::$invoice->number = 'INV-00001';
        self::$invoice->date = time();
        self::$invoice->setTaxes([self::$tax]);
        $items = self::$invoice->items;
        $items[0]['catalog_item'] = self::$item->id;
        self::$invoice->items = $items;

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->total = 150.00;
        self::$invoice2->number = 'INV-00002';
        self::$invoice2->date = time();
        self::$invoice2->items = [
            [
                'name' => 'Test Item —',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
                'catalog_item' => self::$item->id,
            ],
        ];
        self::$invoice2->setTaxes([self::$taxRate]);
        self::$invoice2->saveOrFail();

        self::$invoice3 = new Invoice();
        self::$invoice3->setCustomer(self::$customer);
        self::$invoice3->total = 0.10;
        self::$invoice3->number = 'INV-00003';
        self::$invoice3->date = time();
        self::$invoice3->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
                'catalog_item' => self::$item->id,
            ],
        ];
        self::$invoice3->setTaxes([self::$taxRate]);
        self::$invoice3->saveOrFail();
    }

    public function testMakeLevel3(): void
    {
        $expected = [
            'po_number' => self::$invoice->number,
            'order_date' => date('Y-m-d', self::$invoice->date),
            'ship_to' => [
                'object' => 'address',
                'address1' => self::$customer->address1,
                'address2' => self::$customer->address2,
                'city' => self::$customer->city,
                'state' => self::$customer->state,
                'postal_code' => self::$customer->postal_code,
                'country' => self::$customer->country,
            ],
            'merchant_postal_code' => self::$company->postal_code,
            'summary_commodity_code' => '80161501',
            'line_items' => [
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
            ],
            'tax' => self::$invoice->taxes[0]->amount,
            'shipping' => 0,
        ];

        $level3 = GatewayHelper::makeLevel3([self::$invoice], self::$customer, Money::fromDecimal(self::$invoice->currency, 115));
        $this->validateLevel3($expected, $level3);
    }

    public function testMakeLevel3FromMultipleInvoicesAndConvenienceFee(): void
    {
        $expected = [
            'po_number' => self::$invoice->number,
            'order_date' => date('Y-m-d', self::$invoice->date),
            'ship_to' => [
                'object' => 'address',
                'address1' => self::$customer->address1,
                'address2' => self::$customer->address2,
                'city' => self::$customer->city,
                'state' => self::$customer->state,
                'postal_code' => self::$customer->postal_code,
                'country' => self::$customer->country,
            ],
            'merchant_postal_code' => self::$company->postal_code,
            'summary_commodity_code' => '80161501',
            'line_items' => [
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
                [
                    'description' => 'Adjustment',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 5,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
            ],
            'tax' => 15.0,
            'shipping' => 0.0,
        ];

        $level3 = GatewayHelper::makeLevel3([self::$invoice, self::$invoice2, self::$invoice3], self::$customer, Money::fromDecimal(self::$invoice->currency, 320));
        $this->validateLevel3($expected, $level3);
    }


    public function testMakeLevel3LineItemTaxes(): void
    {
        $expected = [
            'po_number' => self::$invoice->number,
            'order_date' => date('Y-m-d', self::$invoice->date),
            'ship_to' => [
                'object' => 'address',
                'address1' => self::$customer->address1,
                'address2' => self::$customer->address2,
                'city' => self::$customer->city,
                'state' => self::$customer->state,
                'postal_code' => self::$customer->postal_code,
                'country' => self::$customer->country,
            ],
            'merchant_postal_code' => self::$company->postal_code,
            'summary_commodity_code' => '80161501',
            'line_items' => [
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
                [
                    'product_code' => 'test-item',
                    'description' => 'Test Item',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 100,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
                [
                    'description' => 'Adjustment',
                    'commodity_code' => '80161501',
                    'quantity' => 1,
                    'unit_cost' => 5,
                    'unit_of_measure' => 'EA',
                    'discount' => 0,
                ],
            ],
            'tax' => 15.0,
            'shipping' => 0.0,
        ];

        $level3 = GatewayHelper::makeLevel3([self::$invoice, self::$invoice2, self::$invoice3], self::$customer, Money::fromDecimal(self::$invoice->currency, 320));
        $this->validateLevel3($expected, $level3);
    }

    public function testKlarnaLineItems(): void
    {
        $tax1 = new TaxRate();
        $tax1->id = 'tax1';
        $tax1->name = 'tax1';
        $tax1->value = 1;
        $tax1->inclusive = true;
        $tax1->is_percent = true;

        $tax2 = new TaxRate();
        $tax2->id = 'tax2';
        $tax2->name = 'tax2';
        $tax2->value = 2;
        $tax2->inclusive = true;
        $tax2->is_percent = false;

        $tax3 = new TaxRate();
        $tax3->id = 'tax3';
        $tax3->name = 'tax3';
        $tax3->value = 3;
        $tax3->inclusive = false;
        $tax3->is_percent = false;

        $tax4 = new TaxRate();
        $tax4->id = 'tax4';
        $tax4->name = 'tax4';
        $tax4->value = 4;
        $tax4->inclusive = false;
        $tax4->is_percent = true;

        $tax5 = new TaxRate();
        $tax5->id = 'tax5';
        $tax5->name = 'tax5';
        $tax5->value = 5;
        $tax5->inclusive = true;
        $tax5->is_percent = true;

        $tax6 = new TaxRate();
        $tax6->id = 'tax6';
        $tax6->name = 'tax6';
        $tax6->value = 6;
        $tax6->inclusive = true;
        $tax6->is_percent = false;

        $tax7 = new TaxRate();
        $tax7->id = 'tax7';
        $tax7->name = 'tax7';
        $tax7->value = 7;
        $tax7->inclusive = false;
        $tax7->is_percent = false;

        $tax8 = new TaxRate();
        $tax8->id = 'tax8';
        $tax8->name = 'tax8';
        $tax8->value = 8;
        $tax8->inclusive = false;
        $tax8->is_percent = true;


        $invoice4 = new Invoice();
        $invoice4->setCustomer(self::$customer);
        $invoice4->number = 'INV-00004';
        $invoice4->date = time();
        $invoice4->taxes = [
            [
                'tax_rate' => $tax1->toArray(),
            ],
            [
                'tax_rate' => $tax2->toArray(),
            ],
            [
                'tax_rate' => $tax3->toArray(),
            ],
            [
                'tax_rate' => $tax4->toArray(),
            ],
        ];
        $invoice4->discounts = [['amount' => 5.1]];
        $invoice4->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 50,
                'taxes' => [
                    [
                        'tax_rate' => $tax5->toArray(),
                    ],
                    [
                        'tax_rate' => $tax6->toArray(),
                    ],
                    [
                        'tax_rate' => $tax7->toArray(),
                    ],
                    [
                        'tax_rate' => $tax8->toArray(),
                    ],
                ],
                'discounts' => [['amount' => 10.2]],
            ],
            [
                'name' => 'Test Item 2',
                'description' => 'test2',
                'quantity' => 2,
                'unit_cost' => 50,
            ],
            [
                'name' => 'Shipping',
                'description' => 'test3',
                'quantity' => 1,
                'unit_cost' => 50,
                'type' => 'shipping',
            ],
        ];
        $invoice4->saveOrFail();


        $expected = [
            [
                'quantity' => 1.0,
                'amountExcludingTax' => 3597,
                'taxPercentage' => 7245,
                'description' => 'Test Item',
                'id' => 'INV-00004-Test Item',
            ],
            [
                'quantity' => 2.0,
                'amountExcludingTax' => 9235,
                'taxPercentage' => 1728,
                'description' => 'Test Item 2',
                'id' => 'INV-00004-Test Item 2',
            ],
            [
                'quantity' => 1.0,
                'amountExcludingTax' => 10000,
                'taxPercentage' => 1500,
                'description' => 'Test Item',
                'id' => 'INV-00001-Test Item',
            ],
            [
                'quantity' => 1,
                'amountExcludingTax' => 5000,
                'taxPercentage' => 0,
                'description' => 'Shipping',
                'id' => 'Shipping',
            ],
            [
                'description' => 'Rounding Adjustment',
                'quantity' => 1,
                'amountExcludingTax' => 1466,
                'taxPercentage' => 0,
                'id' => 'Adjustment',
            ],
        ];

        $data = GatewayHelper::makeKlarnaLineItems([$invoice4, self::$invoice], Money::fromDecimal(self::$invoice->currency, 300));
        $this->assertEquals($expected, $data);
    }

    public function testMakeLevel3FromEmptyList(): void
    {
        $expected = [
            'order_date' => date('Y-m-d'),
            'ship_to' => [
                'object' => 'address',
                'address1' => 'Test',
                'address2' => 'Address',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
            ],
            'merchant_postal_code' => '78701',
            'summary_commodity_code' => '80161501',
            'line_items' => [
                [
                    'description' => 'Order Summary',
                    'commodity_code' => '80161501',
                    'quantity' => 1.0,
                    'unit_cost' => 180.0,
                    'unit_of_measure' => 'EA',
                    'discount' => 0.0,
                ],
            ],
            'tax' => 20.0,
            'shipping' => 0.0,
        ];

        $level3 = GatewayHelper::makeLevel3([], self::$customer, Money::fromDecimal(self::$invoice->currency, 200.00));
        $this->validateLevel3($expected, $level3);
    }

    private function validateLevel3(array $expected, Level3Data $level3): void
    {
        $result = $level3->toArray();

        // po number can be generated
        if (!isset($expected['po_number'])) {
            $this->assertNotEmpty($result['po_number']);
            unset($result['po_number']);
        }

        foreach ($result['line_items'] as $i => &$lineItem) {
            // product code is generated
            $this->assertNotEmpty($lineItem['product_code']);
            if (!isset($expected['line_items'][$i]['product_code'])) {
                unset($lineItem['product_code']);
            }
        }

        $this->assertEquals($expected, $result);
    }
}
