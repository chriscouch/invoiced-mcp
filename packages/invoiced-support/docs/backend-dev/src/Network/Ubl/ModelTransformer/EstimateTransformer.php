<?php

namespace App\Network\Ubl\ModelTransformer;

use App\AccountsReceivable\Models\Estimate;
use App\Network\Ubl\UblWriter;
use Carbon\CarbonImmutable;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;

class EstimateTransformer extends AbstractDocumentTransformer
{
    /**
     * @param Estimate $model
     */
    public function transform(object $model, array $options): string
    {
        $quote = UblWriter::quote();
        UblWriter::id($quote, $model->number);
        UblWriter::issueDate($quote, CarbonImmutable::createFromTimestamp($model->date)->toDateString());

        if ($model->notes) {
            UblWriter::customerMemo($quote, $model->notes);
        }

        $currency = strtoupper($model->currency);
        UblWriter::pricingCurrency($quote, $currency);

        if ($purchaseOrder = $model->purchase_order) {
            UblWriter::orderReference($quote, $purchaseOrder);
        }

        if ($options['pdf'] ?? true) {
            $locale = $options['locale'] ?? $model->customer()->getLocale();
            $this->addPdf($quote, $model, $locale);
        }

        UblWriter::supplier($quote, $model->tenant(), 'SellerSupplierParty');
        UblWriter::customer($quote, $model->customer(), 'BuyerCustomerParty');

        // TODO: ship to information

        $this->addTax($quote, $model);

        $currency = new Currency($currency); /* @phpstan-ignore-line */
        $parser = new DecimalMoneyParser(new ISOCurrencies());
        $subtotal = $parser->parse((string) $model->subtotal, $currency);
        $total = $parser->parse((string) $model->total, $currency);
        $totalDiscounts = $parser->parse((string) array_reduce($model->discounts, fn ($total, $discount) => $total + $discount->amount, 0), $currency);
        $paid = $parser->parse((string) ($model->deposit * (int) $model->deposit_paid), $currency);
        $balance = $parser->parse((string) $model->deposit, $currency);
        UblWriter::quotedTotal($quote, $subtotal, $total, $totalDiscounts, $paid, $balance);

        $this->addLineItems($quote, $model);

        return (string) $quote->asXML();
    }
}
