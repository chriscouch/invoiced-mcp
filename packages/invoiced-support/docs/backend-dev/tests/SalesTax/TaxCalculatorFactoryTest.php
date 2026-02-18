<?php

namespace App\Tests\SalesTax;

use App\Companies\Models\Company;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\SalesTax\Calculator\InvoicedTaxCalculator;
use App\SalesTax\Libs\TaxCalculatorFactory;
use App\Tests\AppTestCase;
use Mockery;

class TaxCalculatorFactoryTest extends AppTestCase
{
    private function getFactory(): TaxCalculatorFactory
    {
        return new TaxCalculatorFactory(Mockery::mock(AvalaraTaxCalculator::class), Mockery::mock(InvoicedTaxCalculator::class));
    }

    public function testGetDefault(): void
    {
        $company = new Company(['id' => -1]);
        $assessor = $this->getFactory();
        $this->assertInstanceOf(InvoicedTaxCalculator::class, $assessor->get($company));
    }

    public function testGetAvalara(): void
    {
        $company = new Company(['id' => -1]);
        $company->accounts_receivable_settings->tax_calculator = 'avalara';
        $assessor = $this->getFactory();
        $this->assertInstanceOf(AvalaraTaxCalculator::class, $assessor->get($company));
    }
}
