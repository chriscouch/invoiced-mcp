<?php

namespace App\Network\Ubl\ModelTransformer;

use App\AccountsReceivable\Models\CreditNote;
use App\Network\Ubl\UblWriter;
use Carbon\CarbonImmutable;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;

class CreditNoteTransformer extends AbstractDocumentTransformer
{
    /**
     * @param CreditNote $model
     */
    public function transform(object $model, array $options): string
    {
        $creditNote = UblWriter::creditNote();
        UblWriter::id($creditNote, $model->number);
        UblWriter::issueDate($creditNote, CarbonImmutable::createFromTimestamp($model->date)->toDateString());

        if ($model->notes) {
            UblWriter::customerMemo($creditNote, $model->notes);
        }

        $currency = strtoupper($model->currency);
        UblWriter::documentCurrency($creditNote, $currency);

        if ($purchaseOrder = $model->purchase_order) {
            UblWriter::orderReference($creditNote, $purchaseOrder);
        }

        if ($options['pdf'] ?? true) {
            $locale = $options['locale'] ?? $model->customer()->getLocale();
            $this->addPdf($creditNote, $model, $locale);
        }

        UblWriter::supplier($creditNote, $model->tenant());
        UblWriter::customer($creditNote, $model->customer());

        $this->addTax($creditNote, $model);

        $currency = new Currency($currency); /* @phpstan-ignore-line */
        $parser = new DecimalMoneyParser(new ISOCurrencies());
        $subtotal = $parser->parse((string) $model->subtotal, $currency);
        $total = $parser->parse((string) $model->total, $currency);
        $totalDiscounts = $parser->parse((string) array_reduce($model->discounts, fn ($total, $discount) => $total + $discount->amount, 0), $currency);
        $amountPaid = $parser->parse((string) ($model->total - $model->balance), $currency);
        $balance = $parser->parse((string) $model->balance, $currency);
        UblWriter::total($creditNote, $subtotal, $total, $totalDiscounts, $amountPaid, $balance);

        $this->addLineItems($creditNote, $model);

        return (string) $creditNote->asXML();
    }
}
