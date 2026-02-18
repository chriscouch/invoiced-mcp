<?php

namespace App\SubscriptionBilling\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\MoneyFormatter;
use App\SalesTax\Exception\TaxCalculationException;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use Exception;

class PendingItemInvoice
{
    public function __construct(private Customer $customer)
    {
    }

    /**
     * Builds an invoice from pending line items.
     *
     * @throws TaxCalculationException when sales tax cannot be calculated
     */
    public function build(bool $withTaxPreview, ?array $pendingLineItems = null): Invoice
    {
        $invoice = new Invoice(['date' => null]);
        $company = $this->customer->tenant();
        $invoice->tenant_id = (int) $company->id();
        $invoice->setRelation('tenant_id', $company);
        $invoice->setCustomer($this->customer);
        $invoice->currency = $this->customer->calculatePrimaryCurrency();

        // add pending line items
        if ($pendingLineItems) {
            $invoice->setPendingLineItems($pendingLineItems);
        }

        try {
            $invoice->withPending();
        } catch (Exception) {
            // do nothing since this is just a preview
        }

        // add pending credits
        $invoice->items = array_merge(
            (array) $invoice->items,
            $invoice->getPendingCredits()
        );

        // add in taxes
        if ($withTaxPreview) {
            $assessor = $invoice->getSalesTaxCalculator();
            $address = $invoice->getSalesTaxAddress();

            $lineItems = [];
            $moneyFormatter = MoneyFormatter::get();
            foreach ($invoice->items as $item) {
                $amount = $moneyFormatter->normalizeToZeroDecimal($invoice->currency, $item['amount']);
                $lineItems[] = new SalesTaxInvoiceItem($item['name'], $item['quantity'], $amount, $item['catalog_item'], $item['discountable']);
            }
            $salesTaxInvoice = new SalesTaxInvoice($this->customer, $address, $invoice->currency, $lineItems, [
                'preview' => true,
            ]);

            $invoice->taxes = $assessor->assess($salesTaxInvoice);
        }

        return $invoice;
    }
}
