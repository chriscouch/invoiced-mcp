<?php

namespace App\Tests\AccountsReceivable\Operations;

use App\AccountsReceivable\Exception\ConsolidationException;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Operations\InvoiceConsolidator;
use App\CashApplication\Models\Transaction;
use App\Tests\AppTestCase;

class InvoiceConsolidatorTest extends AppTestCase
{
    private static Customer $customer2;
    private static Customer $customer3;
    private static Customer $customer4;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::$customer->consolidated = true;
        self::$customer->saveOrFail();

        self::$customer2 = new Customer();
        self::$customer2->name = '3M';
        self::$customer2->parent_customer = (int) self::$customer->id();
        self::$customer2->consolidated = true;
        self::$customer2->bill_to_parent = true;
        self::$customer2->saveOrFail();

        self::$customer3 = new Customer();
        self::$customer3->name = 'Acme Corp.';
        self::$customer3->parent_customer = (int) self::$customer->id();
        self::$customer3->consolidated = true;
        self::$customer3->bill_to_parent = true;
        self::$customer3->saveOrFail();

        self::$customer4 = new Customer();
        self::$customer4->name = 'Jared';
        self::$customer4->saveOrFail();
    }

    private function getConsolidator(): InvoiceConsolidator
    {
        return new InvoiceConsolidator(self::getService('test.database'));
    }

    public function testConsolidationNotEnabled(): void
    {
        $this->expectException(ConsolidationException::class);

        $consolidator = $this->getConsolidator();

        $consolidator->consolidate(self::$customer4);
    }

    public function testConsolidation(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [['name' => 'Item 1', 'unit_cost' => 1]];
        $invoice1->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Item 2',
                'unit_cost' => 2,
            ],
            [
                'name' => 'Item 3',
                'description' => 'A description',
                'unit_cost' => 3,
            ],
        ];
        $invoice2->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->setCustomer(self::$customer);
        $invoice3->date = strtotime('-3 months');
        $invoice3->items = [
            [
                'name' => 'Item 4',
                'unit_cost' => 4,
            ],
        ];
        $invoice3->tax = 2; /* @phpstan-ignore-line */
        $invoice3->discount = 1; /* @phpstan-ignore-line */
        $invoice3->saveOrFail();

        /** @var Invoice $consolidatedInvoice */
        $consolidatedInvoice = $consolidator->consolidate(self::$customer);

        // check the results
        $this->assertInstanceOf(Invoice::class, $consolidatedInvoice);
        $this->assertEquals(self::$customer->id(), $consolidatedInvoice->customer);
        $this->assertEquals(11, $consolidatedInvoice->total);
        $this->assertEquals(0, $consolidatedInvoice->amount_credited);
        $this->assertEquals(0, $consolidatedInvoice->amount_paid);
        $this->assertEquals(11, $consolidatedInvoice->balance);
        $this->assertFalse($consolidatedInvoice->closed);
        $this->assertFalse($consolidatedInvoice->paid);

        $expectedItems = [
            [
                'name' => 'Item 4',
                'unit_cost' => 4,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 2',
                'unit_cost' => 2,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 3',
                'description' => 'A description',
                'unit_cost' => 3,
                'quantity' => 1,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
        ];
        $this->checkLineItems($expectedItems, $consolidatedInvoice->items());

        // check the consolidated invoice association
        $this->assertEquals($consolidatedInvoice->id(), $invoice3->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice3->voided);
        $this->assertEquals($consolidatedInvoice->id(), $invoice1->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice1->voided);
        $this->assertEquals($consolidatedInvoice->id(), $invoice2->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice2->voided);
    }

    public function testConsolidationCustomerHierarchy(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer2);
        $invoice1->items = [['name' => 'Item 1', 'unit_cost' => 1]];
        $invoice1->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer2);
        $invoice2->items = [
            [
                'name' => 'Item 2',
                'unit_cost' => 2,
            ],
            [
                'name' => 'Item 3',
                'description' => 'A description',
                'unit_cost' => 3,
            ],
        ];
        $invoice2->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->setCustomer(self::$customer3);
        $invoice3->date = strtotime('-3 months');
        $invoice3->items = [
            [
                'name' => 'Item 4',
                'unit_cost' => 4,
            ],
        ];
        $invoice3->tax = 2; /* @phpstan-ignore-line */
        $invoice3->discount = 1; /* @phpstan-ignore-line */
        $invoice3->saveOrFail();

        /** @var Invoice $consolidatedInvoice */
        $consolidatedInvoice = $consolidator->consolidate(self::$customer);

        // check the results
        $this->assertInstanceOf(Invoice::class, $consolidatedInvoice);
        $this->assertEquals(self::$customer->id(), $consolidatedInvoice->customer);
        $this->assertEquals(11, $consolidatedInvoice->total);
        $this->assertEquals(0, $consolidatedInvoice->amount_credited);
        $this->assertEquals(0, $consolidatedInvoice->amount_paid);
        $this->assertEquals(11, $consolidatedInvoice->balance);
        $this->assertFalse($consolidatedInvoice->closed);
        $this->assertFalse($consolidatedInvoice->paid);

        $expectedItems = [
            [
                'name' => 'Item 4',
                'unit_cost' => 4,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 2',
                'unit_cost' => 2,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 3',
                'description' => 'A description',
                'unit_cost' => 3,
                'quantity' => 1,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
        ];
        $this->checkLineItems($expectedItems, $consolidatedInvoice->items());

        // check the consolidated invoice association
        $this->assertEquals($consolidatedInvoice->id(), $invoice3->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice3->voided);
        $this->assertEquals($consolidatedInvoice->id(), $invoice1->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice1->voided);
        $this->assertEquals($consolidatedInvoice->id(), $invoice2->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice2->voided);
    }

    public function testConsolidationTaxesDiscounts(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [['name' => 'Item 1', 'unit_cost' => 10]];
        $invoice1->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Item 2',
                'unit_cost' => 2,
                'taxes' => [
                    [
                        'amount' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Item 3',
                'description' => 'A description',
                'unit_cost' => 3,
                'discounts' => [
                    [
                        'amount' => 2,
                    ],
                ],
            ],
        ];
        $invoice2->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->setCustomer(self::$customer);
        $invoice3->date = strtotime('-3 months');
        $invoice3->items = [
            [
                'name' => 'Item 4',
                'unit_cost' => 4,
            ],
        ];
        $invoice3->tax = 2; /* @phpstan-ignore-line */
        $invoice3->discount = 1; /* @phpstan-ignore-line */
        $invoice3->saveOrFail();

        $creditNote1 = new CreditNote();
        $creditNote1->setCustomer(self::$customer);
        $creditNote1->setInvoice($invoice1);
        $creditNote1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'discounts' => [
                    [
                        'amount' => 0.5,
                    ],
                ],
                'taxes' => [
                    [
                        'amount' => 1,
                    ],
                ],
            ],
        ];
        $creditNote1->tax = 3; /* @phpstan-ignore-line */
        $creditNote1->discount = 2; /* @phpstan-ignore-line */
        $creditNote1->saveOrFail();

        /** @var Invoice $consolidatedInvoice */
        $consolidatedInvoice = $consolidator->consolidate(self::$customer);

        // check the results
        $this->assertInstanceOf(Invoice::class, $consolidatedInvoice);
        $this->assertEquals(self::$customer->id(), $consolidatedInvoice->customer);
        $this->assertEquals(16.5, $consolidatedInvoice->total);
        $this->assertEquals(0, $consolidatedInvoice->amount_credited);
        $this->assertEquals(0, $consolidatedInvoice->amount_paid);
        $this->assertEquals(16.5, $consolidatedInvoice->balance);
        $this->assertFalse($consolidatedInvoice->closed);
        $this->assertFalse($consolidatedInvoice->paid);

        $expectedItems = [
            [
                'name' => 'Item 4',
                'unit_cost' => 4,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 1',
                'unit_cost' => 10,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 2',
                'unit_cost' => 2,
                'quantity' => 1,
                'description' => '',
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [
                    [
                        'amount' => 1,
                    ],
                ],
                'type' => null,
            ],
            [
                'name' => 'Item 3',
                'description' => 'A description',
                'unit_cost' => 3,
                'quantity' => 1,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [
                    [
                        'amount' => 2,
                    ],
                ],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 1',
                'description' => '',
                'unit_cost' => 1,
                'quantity' => -1,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [
                    [
                        'amount' => -0.5,
                    ],
                ],
                'taxable' => true,
                'taxes' => [
                    [
                        'amount' => -1,
                    ],
                ],
                'type' => null,
            ],
        ];
        $this->checkLineItems($expectedItems, $consolidatedInvoice->items());

        $taxes = $consolidatedInvoice->taxes();
        $this->assertCount(2, $taxes);
        $this->assertEquals(2, $taxes[0]['amount']);
        $this->assertEquals(-3, $taxes[1]['amount']);

        $discounts = $consolidatedInvoice->discounts();
        $this->assertCount(2, $discounts);
        $this->assertEquals(1, $discounts[0]['amount']);
        $this->assertEquals(-2, $discounts[1]['amount']);

        // check the consolidated invoice association
        $this->assertEquals($consolidatedInvoice->id(), $invoice3->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice3->voided);
        $this->assertEquals($consolidatedInvoice->id(), $invoice1->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice1->voided);
        $this->assertEquals($consolidatedInvoice->id(), $invoice2->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice2->voided);
        $this->assertEquals($consolidatedInvoice->id(), $creditNote1->refresh()->consolidated_invoice_id);
        $this->assertTrue($creditNote1->voided);
    }

    public function testConsolidationCreditNotePartialPaid(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $invoice1->saveOrFail();

        $creditNote1 = new CreditNote();
        $creditNote1->setCustomer(self::$customer);
        $creditNote1->setInvoice($invoice1);
        $creditNote1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 3,
            ],
        ];
        $creditNote1->saveOrFail();

        /** @var Invoice $consolidatedInvoice */
        $consolidatedInvoice = $consolidator->consolidate(self::$customer);

        // check the results
        $this->assertInstanceOf(Invoice::class, $consolidatedInvoice);
        $this->assertEquals(self::$customer->id(), $consolidatedInvoice->customer);
        $this->assertEquals(2, $consolidatedInvoice->total);
        $this->assertEquals(0, $consolidatedInvoice->amount_credited);
        $this->assertEquals(0, $consolidatedInvoice->amount_paid);
        $this->assertEquals(2, $consolidatedInvoice->balance);
        $this->assertFalse($consolidatedInvoice->closed);
        $this->assertFalse($consolidatedInvoice->paid);

        $expectedItems = [
            [
                'name' => 'Item 1',
                'description' => '',
                'unit_cost' => 1,
                'quantity' => 5,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 1',
                'description' => '',
                'unit_cost' => 1,
                'quantity' => -3,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
        ];
        $this->checkLineItems($expectedItems, $consolidatedInvoice->items());

        // check the consolidated invoice association
        $this->assertEquals($consolidatedInvoice->id(), $invoice1->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice1->voided);
        $this->assertEquals($consolidatedInvoice->id(), $creditNote1->refresh()->consolidated_invoice_id);
        $this->assertTrue($creditNote1->voided);
    }

    public function testConsolidationCreditNotePaid(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $invoice1->saveOrFail();

        $creditNote1 = new CreditNote();
        $creditNote1->setCustomer(self::$customer);
        $creditNote1->setInvoice($invoice1);
        $creditNote1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $creditNote1->saveOrFail();

        $this->assertNull($consolidator->consolidate(self::$customer));
    }

    public function testConsolidationCreditNoteForPreviousPeriodInvoice(): void
    {
        $consolidator = $this->getConsolidator();

        // create a previously consolidated invoice
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->consolidated = true;
        $invoice1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $invoice1->saveOrFail();

        // create invoices
        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 10,
            ],
        ];
        $invoice2->saveOrFail();

        $creditNote1 = new CreditNote();
        $creditNote1->setCustomer(self::$customer);
        $creditNote1->setInvoice($invoice1);
        $creditNote1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 3,
            ],
        ];
        $creditNote1->saveOrFail();

        /** @var Invoice $consolidatedInvoice */
        $consolidatedInvoice = $consolidator->consolidate(self::$customer);

        // check the results
        $this->assertInstanceOf(Invoice::class, $consolidatedInvoice);
        $this->assertEquals(self::$customer->id(), $consolidatedInvoice->customer);
        $this->assertEquals(7.0, $consolidatedInvoice->total);
        $this->assertEquals(0, $consolidatedInvoice->amount_credited);
        $this->assertEquals(0, $consolidatedInvoice->amount_paid);
        $this->assertEquals(7.0, $consolidatedInvoice->balance);
        $this->assertFalse($consolidatedInvoice->closed);
        $this->assertFalse($consolidatedInvoice->paid);

        $expectedItems = [
            [
                'name' => 'Item 1',
                'description' => '',
                'unit_cost' => 1,
                'quantity' => 10,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
            [
                'name' => 'Item 1',
                'description' => '',
                'unit_cost' => 1,
                'quantity' => -3,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
        ];
        $this->checkLineItems($expectedItems, $consolidatedInvoice->items());

        // check the consolidated invoice association
        $this->assertEquals($consolidatedInvoice->id(), $invoice2->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice2->voided);
        $this->assertEquals($consolidatedInvoice->id(), $creditNote1->refresh()->consolidated_invoice_id);
        $this->assertTrue($creditNote1->voided);
    }

    public function testConsolidationBadDebtInvoices(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $invoice1->closed = true;
        $invoice1->saveOrFail();

        $this->assertNull($consolidator->consolidate(self::$customer));
    }

    public function testConsolidationPartialPaidInvoices(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $invoice1->saveOrFail();

        $payment = new Transaction();
        $payment->setInvoice($invoice1);
        $payment->amount = 3;
        $payment->saveOrFail();

        /** @var Invoice $consolidatedInvoice */
        $consolidatedInvoice = $consolidator->consolidate(self::$customer);

        // check the results
        $this->assertInstanceOf(Invoice::class, $consolidatedInvoice);
        $this->assertEquals(self::$customer->id(), $consolidatedInvoice->customer);
        $this->assertEquals(5, $consolidatedInvoice->total);
        $this->assertEquals(0, $consolidatedInvoice->amount_credited);
        $this->assertEquals(3, $consolidatedInvoice->amount_paid);
        $this->assertEquals(2, $consolidatedInvoice->balance);
        $this->assertFalse($consolidatedInvoice->closed);
        $this->assertFalse($consolidatedInvoice->paid);

        $expectedItems = [
            [
                'name' => 'Item 1',
                'description' => '',
                'unit_cost' => 1,
                'quantity' => 5,
                'catalog_item' => null,
                'discountable' => true,
                'discounts' => [],
                'taxable' => true,
                'taxes' => [],
                'type' => null,
            ],
        ];
        $this->checkLineItems($expectedItems, $consolidatedInvoice->items());

        // check the consolidated invoice association
        $this->assertEquals($consolidatedInvoice->id(), $invoice1->refresh()->consolidated_invoice_id);
        $this->assertTrue($invoice1->voided);

        // check the payment association
        $this->assertEquals($consolidatedInvoice->id(), $payment->refresh()->invoice);
    }

    public function testConsolidationPaidInvoices(): void
    {
        $consolidator = $this->getConsolidator();

        // create invoices
        $invoice1 = new Invoice();
        $invoice1->setCustomer(self::$customer);
        $invoice1->items = [
            [
                'name' => 'Item 1',
                'unit_cost' => 1,
                'quantity' => 5,
            ],
        ];
        $invoice1->saveOrFail();

        $payment = new Transaction();
        $payment->setInvoice($invoice1);
        $payment->amount = $invoice1->balance;
        $payment->saveOrFail();

        $this->assertNull($consolidator->consolidate(self::$customer));
    }

    private function checkLineItems(array $expected, array $items): void
    {
        $sort = function ($item1, $item2) {
            return strcmp($item1['name'], $item2['name']);
        };
        usort($expected, $sort);
        usort($items, $sort);
        // clean up some extra properties for easier comparison
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['object']);
            unset($item['amount']);
            unset($item['metadata']);
            unset($item['created_at']);
            unset($item['updated_at']);

            foreach ($item['taxes'] as &$tax) {
                unset($tax['id']);
                unset($tax['object']);
                unset($tax['tax_rate']);
                unset($tax['updated_at']);
            }

            foreach ($item['discounts'] as &$discount) {
                unset($discount['id']);
                unset($discount['object']);
                unset($discount['coupon']);
                unset($discount['expires']);
                unset($discount['from_payment_terms']);
                unset($discount['updated_at']);
            }
        }
        $this->assertEquals($expected, $items);
    }
}
