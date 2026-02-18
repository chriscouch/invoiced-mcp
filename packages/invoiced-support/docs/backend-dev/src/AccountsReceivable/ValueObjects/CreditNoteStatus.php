<?php

namespace App\AccountsReceivable\ValueObjects;

use App\AccountsReceivable\Models\CreditNote;

class CreditNoteStatus implements \Stringable
{
    const DRAFT = 'draft';
    const OPEN = 'open';
    const PAID = 'paid';
    const CLOSED = 'closed';
    const VOIDED = 'voided';

    private string $status;

    public function __construct(private CreditNote $creditNote)
    {
        $this->status = $this->determine();
    }

    public function __toString(): string
    {
        return $this->status;
    }

    /**
     * Gets the computed status.
     */
    public function get(): string
    {
        return $this->status;
    }

    /**
     * Computes the credit note status.
     */
    private function determine(): string
    {
        if ($this->creditNote->voided) {
            return self::VOIDED;
        }

        if ($this->creditNote->draft) {
            return self::DRAFT;
        }

        if ($this->creditNote->paid) {
            return self::PAID;
        }

        if ($this->creditNote->closed) {
            return self::CLOSED;
        }

        return self::OPEN;
    }
}
