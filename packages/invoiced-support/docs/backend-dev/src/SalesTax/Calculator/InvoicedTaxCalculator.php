<?php

namespace App\SalesTax\Calculator;

use App\AccountsReceivable\Models\Tax;
use App\SalesTax\Interfaces\TaxCalculatorInterface;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\Models\TaxRule;
use CommerceGuys\Addressing\Address;

/**
 * Performs tax calculation through the Invoiced
 * basic sales tax calculator.
 */
class InvoicedTaxCalculator implements TaxCalculatorInterface
{
    public function assess(SalesTaxInvoice $salesTaxInvoice): array
    {
        // is the customer taxable?
        $customer = $salesTaxInvoice->getCustomer();
        if (!$customer->taxable) {
            return [];
        }

        // start with any customer taxes
        $customer = $salesTaxInvoice->getCustomer();
        if ($customerTaxes = $customer->taxes) {
            $taxRates = $customerTaxes;
        } else {
            $taxRates = [];
        }

        // apply any tax rules
        foreach (TaxRule::all() as $rule) {
            if ($this->matches($rule, $salesTaxInvoice->getAddress())) {
                $taxRates[] = $rule->tax_rate;
            }
        }

        // return the de-duped tax rates
        $taxRates = array_values(array_unique($taxRates));

        return Tax::expandList($taxRates);
    }

    /**
     * Checks if a tax rule applies to a customer.
     */
    public function matches(TaxRule $rule, Address $address): bool
    {
        if ($rule->country && $rule->country != $address->getCountryCode()) {
            return false;
        }

        if ($rule->state && $rule->state != $address->getAdministrativeArea()) {
            return false;
        }

        return true;
    }

    public function adjust(SalesTaxInvoice $salesTaxInvoice): array
    {
        // The adjustment process yields the same result as
        // the initial tax assessment
        return $this->assess($salesTaxInvoice);
    }

    public function void(SalesTaxInvoice $salesTaxInvoice): void
    {
        // there is nothing to void on an external system
    }
}
