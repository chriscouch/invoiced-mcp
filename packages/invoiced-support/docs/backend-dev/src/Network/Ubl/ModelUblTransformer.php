<?php

namespace App\Network\Ubl;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Network\Exception\UblValidationException;
use App\Network\Interfaces\ModelTransformerInterface;
use App\Network\Ubl\ModelTransformer\CreditNoteTransformer;
use App\Network\Ubl\ModelTransformer\EstimateTransformer;
use App\Network\Ubl\ModelTransformer\InvoiceTransformer;
use App\Network\Ubl\ModelTransformer\StatementTransformer;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\Libs\OpenItemStatement;

/**
 * This class transforms an Invoiced model into
 * a Universal Business Language (UBL) document.
 */
class ModelUblTransformer
{
    public function __construct(
        private InvoiceTransformer $invoiceTransformer,
        private CreditNoteTransformer $creditNoteTransformer,
        private EstimateTransformer $estimateTransformer,
        private StatementTransformer $statementTransformer,
    ) {
    }

    /**
     * Transforms a model into a UBL document.
     *
     * @throws UblValidationException
     *
     * @return string UBL document as an XML string
     */
    public function transform(object $model, array $options = []): string
    {
        return $this->getTransformer($model)->transform($model, $options);
    }

    /**
     * @throws UblValidationException
     */
    private function getTransformer(object $model): ModelTransformerInterface
    {
        return match ($model::class) {
            Invoice::class => $this->invoiceTransformer,
            CreditNote::class => $this->creditNoteTransformer,
            Estimate::class => $this->estimateTransformer,
            BalanceForwardStatement::class, OpenItemStatement::class => $this->statementTransformer,
            default => throw new UblValidationException('Object type not supported for transformation: '.$model::class),
        };
    }
}
