<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Exception\InvoiceGenerationException;
use App\AccountsReceivable\Libs\PaymentTermsFactory;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ShippingDetail;
use App\Core\I18n\ValueObjects\Money;

class GenerateEstimateInvoice
{
    private const INVOICE_EXCLUDE = [
        'id',
        'sent',
        'number',
        'viewed',
        'closed',
        'draft',
        'approved',
        'status',
        'invoice',
    ];

    /**
     * Creates an invoice from the estimate.
     *
     * @throws InvoiceGenerationException when the invoice cannot be generated
     */
    public function generateInvoice(Estimate $estimate): Invoice
    {
        $params = $estimate->toArray();

        // remove estimate properties that will not be carried over
        // to the new invoice
        foreach (self::INVOICE_EXCLUDE as $k) {
            unset($params[$k]);
        }

        $params['date'] = time();

        if ('Estimate' == $params['name']) {
            unset($params['name']);
        }

        // clear item and item rate ids
        // nested loops..yikes!
        foreach ($params['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            foreach (['discounts', 'taxes'] as $type) {
                foreach ($item[$type] as &$rate) {
                    unset($rate['id']);
                }
            }
        }

        // clear rate ids
        foreach (['discounts', 'taxes', 'shipping'] as $type) {
            foreach ($params[$type] as &$rate) {
                unset($rate['id']);
            }
        }

        // add an early discount if the customer's payment terms support it
        $terms = PaymentTermsFactory::get((string) $params['payment_terms']);
        $subtotal = Money::fromDecimal($estimate->currency, $estimate->subtotal);
        if ($discount = $terms->getEarlyDiscount($subtotal)) {
            $params['discounts'][] = $discount;
        }

        // clone ship to, if set
        if ($params['ship_to'] instanceof ShippingDetail) {
            $params['ship_to'] = $params['ship_to']->makeCopy();
        }

        $invoice = new Invoice();
        foreach ($params as $k => $v) {
            $invoice->$k = $v;
        }

        if (!$invoice->save()) {
            throw new InvoiceGenerationException('Could not generate invoice from estimate: '.$invoice->getErrors());
        }

        $estimate->setInvoice($invoice);
        $estimate->skipClosedCheck()->save();

        return $invoice;
    }
}
