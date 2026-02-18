<?php

namespace App\Statements\StatementLines\OpenItem;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\OpenItemStatementLineInterface;

final class OpenInvoiceStatementLine implements OpenItemStatementLineInterface
{
    public function __construct(private Invoice $invoice)
    {
    }

    public function build(): array
    {
        return [
            'customer' => $this->invoice->customer(),
            'number' => $this->invoice->number,
            'url' => $this->invoice->url,
            'date' => $this->invoice->date,
            'dueDate' => $this->invoice->due_date,
            'total' => $this->getLineTotal()->toDecimal(),
            'balance' => $this->getLineBalance()->toDecimal(),
            'invoice' => $this->invoice,
        ];
    }

    public function getDate(): int
    {
        return $this->invoice->date;
    }

    public function getLineTotal(): Money
    {
        return $this->invoice->getTotal();
    }

    public function getLineBalance(): Money
    {
        return Money::fromDecimal($this->invoice->currency, $this->invoice->balance);
    }
}
