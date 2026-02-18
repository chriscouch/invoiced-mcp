<?php

namespace App\Network\Ubl\ModelTransformer;

use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Libs\OpenItemStatement;
use App\Network\Interfaces\ModelTransformerInterface;
use App\Network\Ubl\UblWriter;
use Carbon\CarbonImmutable;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;
use SimpleXMLElement;

class StatementTransformer implements ModelTransformerInterface
{
    /**
     * @param AbstractStatement $model
     */
    public function transform(object $model, array $options): string
    {
        $statement = UblWriter::statement();
        UblWriter::id($statement, $model->customer->number.'-'.date('Ymd', $model->end));

        $start = $model->start ? CarbonImmutable::createFromTimestamp($model->start) : null;
        $end = CarbonImmutable::createFromTimestamp($model->end ?? time());
        UblWriter::issueDate($statement, $end->toDateString());

        $currency = new Currency(strtoupper($model->currency)); /* @phpstan-ignore-line */
        UblWriter::documentCurrency($statement, $currency->getCode());

        $parser = new DecimalMoneyParser(new ISOCurrencies());

        UblWriter::statementTotal($statement, $parser->parse((string) $model->totalInvoiced, $currency), 'TotalDebitAmount');
        UblWriter::statementTotal($statement, $parser->parse((string) $model->totalPaid, $currency), 'TotalCreditAmount');
        UblWriter::statementTotal($statement, $parser->parse((string) $model->balance, $currency), 'TotalBalanceAmount');

        UblWriter::statementPeriod($statement, $start, $end);

        if ($options['pdf'] ?? true) {
            $locale = $options['locale'] ?? $model->customer->getLocale();
            $this->addPdf($statement, $model, $locale);
        }

        UblWriter::supplier($statement, $model->getSendCompany());
        UblWriter::customer($statement, $model->customer);

        if ($model instanceof BalanceForwardStatement) {
            foreach ($model->unifiedDetail as $index => $item) {
                $balanceBroughtForward = 'previous_balance' == $item['_type'];
                UblWriter::statementLineItem($statement, (string) ($index + 1), $parser->parse((string) ($item['total'] ?? $item['amount']), $currency), $item['number'], $balanceBroughtForward);
                // TODO: can add more details like invoice, credit note, and payment
            }
        }

        if ($model instanceof OpenItemStatement) {
            UblWriter::statementLineItem($statement, '1', $parser->parse((string) $model->previousBalance, $currency), 'Previous Balance', true);

            foreach ($model->accountDetail as $index => $item) {
                UblWriter::statementLineItem($statement, (string) ($index + 2), $parser->parse((string) $item['total'], $currency), $item['number']);
                // TODO: can add more details like invoice, credit note, and payment
            }
        }

        return (string) $statement->asXML();
    }

    private function addPdf(SimpleXMLElement $parent, AbstractStatement $model, string $locale): void
    {
        $pdf = $model->getPdfBuilder();
        if (!$pdf) {
            return;
        }

        UblWriter::pdf($parent, $pdf, $locale);
    }
}
