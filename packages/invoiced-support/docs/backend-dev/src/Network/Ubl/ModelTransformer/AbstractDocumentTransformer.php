<?php

namespace App\Network\Ubl\ModelTransformer;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Network\Interfaces\ModelTransformerInterface;
use App\Network\Ubl\UblWriter;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;
use SimpleXMLElement;

abstract class AbstractDocumentTransformer implements ModelTransformerInterface
{
    protected function addLineItems(SimpleXMLElement $parent, ReceivableDocument $model): void
    {
        $currency = new Currency(strtoupper($model->currency)); /* @phpstan-ignore-line */
        $parser = new DecimalMoneyParser(new ISOCurrencies());
        foreach ($model->items as $index => $line) {
            $id = (string) ($index + 1);
            $name = $line->name;
            $description = $line->description;
            $quantity = (string) $line->quantity;
            $unitCost = $parser->parse((string) $line->unit_cost, $currency);
            $amount = $parser->parse((string) $line->amount, $currency);
            if ('estimate' == $model->object) {
                UblWriter::quoteLineItem($parent, $id, $name, $description, $quantity, $unitCost, $amount);
            } else {
                UblWriter::invoiceLineItem($parent, $id, $name, $description, $quantity, $unitCost, $amount);
            }
        }
    }

    protected function addTax(SimpleXMLElement $parent, ReceivableDocument $model): ?SimpleXMLElement
    {
        if (0 == count($model->taxes)) {
            return null;
        }

        $currency = new Currency(strtoupper($model->currency)); /* @phpstan-ignore-line */
        $parser = new DecimalMoneyParser(new ISOCurrencies());
        $taxTotal = UblWriter::cac($parent, 'TaxTotal');
        foreach ($model->taxes as $tax) {
            UblWriter::taxAmount($taxTotal, $parser->parse((string) $tax->amount, $currency));
        }

        return $taxTotal;
    }

    protected function addPdf(SimpleXMLElement $parent, ReceivableDocument $model, string $locale): void
    {
        $pdf = $model->getPdfBuilder();
        if (!$pdf) {
            return;
        }

        UblWriter::pdf($parent, $pdf, $locale);
    }
}
