<?php

namespace App\Tests\SalesTax;

use App\AccountsReceivable\Models\Customer;
use App\SalesTax\Calculator\InvoicedTaxCalculator;
use App\SalesTax\Models\TaxRate;
use App\SalesTax\Models\TaxRule;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\Tests\AppTestCase;
use CommerceGuys\Addressing\Address;

class InvoicedTaxCalculatorTest extends AppTestCase
{
    private static TaxRule $rule;
    private static TaxRule $rule2;
    private static TaxRule $rule3;
    private static TaxRate $rate1;
    private static TaxRate $rate2;
    private static TaxRate $rate3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::$rate1 = new TaxRate();
        self::$rate1->id = 'tax-rate-default';
        self::$rate1->name = 'Default';
        self::$rate1->value = 5;
        self::$rate1->saveOrFail();

        self::$rate2 = new TaxRate();
        self::$rate2->id = 'tax-rate-ca';
        self::$rate2->name = 'Canada';
        self::$rate2->value = 6;
        self::$rate2->saveOrFail();

        self::$rate3 = new TaxRate();
        self::$rate3->id = 'tax-rate-us-tx';
        self::$rate3->name = 'Texas';
        self::$rate3->value = 7;
        self::$rate3->saveOrFail();

        self::$rule = new TaxRule();
        self::$rule->tax_rate = 'tax-rate-default';
        self::$rule->saveOrFail();

        self::$rule2 = new TaxRule();
        self::$rule2->tax_rate = 'tax-rate-ca';
        self::$rule2->country = 'CA';
        self::$rule2->saveOrFail();

        self::$rule3 = new TaxRule();
        self::$rule3->tax_rate = 'tax-rate-us-tx';
        self::$rule3->state = 'TX';
        self::$rule3->country = 'US';
        self::$rule3->saveOrFail();
    }

    public function testAssessCustomerTaxes(): void
    {
        $calculator = new InvoicedTaxCalculator();
        $customer = new Customer();
        $customer->taxes = ['tax-rate-ca', 'tax-rate-us-tx'];
        $address = new Address();
        $salesTaxInvoice = new SalesTaxInvoice($customer, $address, 'usd', []);

        $this->assertEquals([
            ['tax_rate' => self::$rate2->toArray()],
            ['tax_rate' => self::$rate3->toArray()],
            ['tax_rate' => self::$rate1->toArray()],
        ], $calculator->assess($salesTaxInvoice));
    }

    public function testAssessDeduplicateTaxes(): void
    {
        $calculator = new InvoicedTaxCalculator();

        $customer = new Customer();
        $customer->taxes = ['tax-rate-ca', 'tax-rate-us-tx'];
        $address = new Address();
        $salesTaxInvoice = new SalesTaxInvoice($customer, $address, 'usd', []);

        $this->assertEquals([
            ['tax_rate' => self::$rate2->toArray()],
            ['tax_rate' => self::$rate3->toArray()],
            ['tax_rate' => self::$rate1->toArray()],
        ], $calculator->assess($salesTaxInvoice));
    }

    public function testAssess(): void
    {
        $calculator = new InvoicedTaxCalculator();

        $customer = new Customer();
        $address = new Address('US', 'TX');
        $salesTaxInvoice = new SalesTaxInvoice($customer, $address, 'usd', []);

        $this->assertEquals([
            ['tax_rate' => self::$rate1->toArray()],
            ['tax_rate' => self::$rate3->toArray()],
        ], $calculator->assess($salesTaxInvoice));
    }

    public function testAssessNotTaxable(): void
    {
        $calculator = new InvoicedTaxCalculator();

        $customer = new Customer();
        $customer->taxable = false;
        $address = new Address('US', 'TX');
        $salesTaxInvoice = new SalesTaxInvoice($customer, $address, 'usd', []);

        $this->assertEquals([], $calculator->assess($salesTaxInvoice));
    }

    public function testMatchesNoConstraints(): void
    {
        $calculator = new InvoicedTaxCalculator();

        $rule = new TaxRule();

        $address = new Address('US', 'TX');

        $this->assertTrue($calculator->matches($rule, $address));
    }

    public function testMatchesCountryConstraint(): void
    {
        $calculator = new InvoicedTaxCalculator();

        $rule = new TaxRule();
        $rule->country = 'CA';

        $address = new Address('US', 'TX');

        $this->assertFalse($calculator->matches($rule, $address));

        $rule->country = 'US';
        $this->assertTrue($calculator->matches($rule, $address));
    }

    public function testMatchesStateConstraint(): void
    {
        $calculator = new InvoicedTaxCalculator();

        $rule = new TaxRule();
        $rule->state = 'OK';

        $address = new Address('US', 'TX');

        $this->assertFalse($calculator->matches($rule, $address));

        $rule->state = 'TX';
        $this->assertTrue($calculator->matches($rule, $address));
    }
}
