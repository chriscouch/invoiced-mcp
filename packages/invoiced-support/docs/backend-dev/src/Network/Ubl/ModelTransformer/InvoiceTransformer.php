<?php

namespace App\Network\Ubl\ModelTransformer;

use App\AccountsReceivable\Models\Invoice;
use App\Network\Ubl\UblWriter;
use Carbon\CarbonImmutable;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;

class InvoiceTransformer extends AbstractDocumentTransformer
{
    /**
     * @param Invoice $model
     */
    public function transform(object $model, array $options): string
    {
        $invoice = UblWriter::invoice();
        UblWriter::id($invoice, $model->number);
        UblWriter::issueDate($invoice, CarbonImmutable::createFromTimestamp($model->date)->toDateString());

        if ($model->due_date) {
            UblWriter::dueDate($invoice, CarbonImmutable::createFromTimestamp($model->due_date)->toDateString());
        }

        if ($model->notes) {
            UblWriter::customerMemo($invoice, $model->notes);
        }

        $currency = strtoupper($model->currency);
        UblWriter::documentCurrency($invoice, $currency);

        if ($purchaseOrder = $model->purchase_order) {
            UblWriter::orderReference($invoice, $purchaseOrder);
        }

        if ($options['pdf'] ?? true) {
            $locale = $options['locale'] ?? $model->customer()->getLocale();
            $this->addPdf($invoice, $model, $locale);
        }

        UblWriter::supplier($invoice, $model->tenant());
        UblWriter::customer($invoice, $model->customer());

        if ($model->payment_terms) {
            UblWriter::paymentTerms($invoice, $model->payment_terms);
        }

        // TODO: ship to information

        $this->addTax($invoice, $model);

        $currency = new Currency($currency); /* @phpstan-ignore-line */
        $parser = new DecimalMoneyParser(new ISOCurrencies());
        $subtotal = $parser->parse((string) $model->subtotal, $currency);
        $total = $parser->parse((string) $model->total, $currency);
        $totalDiscounts = $parser->parse((string) array_reduce($model->discounts, fn ($total, $discount) => $total + $discount->amount, 0), $currency);
        $amountPaid = $parser->parse((string) ($model->total - $model->balance), $currency);
        $balance = $parser->parse((string) $model->balance, $currency);
        UblWriter::total($invoice, $subtotal, $total, $totalDiscounts, $amountPaid, $balance);

        $this->addLineItems($invoice, $model);

        return (string) $invoice->asXML();
    }
}
