<?php

namespace App\Statements\StatementLines\OpenItem;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\OpenItemStatementLineInterface;

final class OpenCreditNoteStatementLine implements OpenItemStatementLineInterface
{
    public function __construct(private CreditNote $creditNote)
    {
    }

    public function build(): array
    {
        return [
            'customer' => $this->creditNote->customer(),
            'number' => $this->creditNote->number,
            'url' => $this->creditNote->url,
            'date' => $this->creditNote->date,
            'dueDate' => null,
            'total' => $this->getLineTotal()->toDecimal(),
            'balance' => $this->getLineBalance()->toDecimal(),
            'creditNote' => $this->creditNote,
        ];
    }

    public function getDate(): int
    {
        return $this->creditNote->date;
    }

    public function getLineTotal(): Money
    {
        return $this->creditNote->getTotal()->negated();
    }

    public function getLineBalance(): Money
    {
        return Money::fromDecimal($this->creditNote->currency, $this->creditNote->balance)->negated();
    }
}
