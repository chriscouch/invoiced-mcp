<?php

namespace App\SalesTax\Libs;

use App\Companies\Models\Company;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\SalesTax\Calculator\InvoicedTaxCalculator;
use App\SalesTax\Interfaces\TaxCalculatorInterface;

/**
 * Determines the tax rate(s) to apply in a given billing scenario.
 */
class TaxCalculatorFactory
{
    public function __construct(private AvalaraTaxCalculator $avalaraTaxCalculator, private InvoicedTaxCalculator $invoicedTaxCalculator)
    {
    }

    /**
     * Gets the tax calculator used for this company.
     */
    public function get(Company $company): TaxCalculatorInterface
    {
        if ('avalara' == $company->accounts_receivable_settings->tax_calculator) {
            return $this->avalaraTaxCalculator;
        }

        return $this->invoicedTaxCalculator;
    }
}
