<?php

namespace App\AccountsReceivable\ValueObjects;

use App\AccountsReceivable\Models\Estimate;

class EstimateStatus implements \Stringable
{
    const INVOICED = 'invoiced';
    const APPROVED = 'approved';
    const DECLINED = 'declined';
    const EXPIRED = 'expired';
    const VIEWED = 'viewed';
    const SENT = 'sent';
    const NOT_SENT = 'not_sent';
    const DRAFT = 'draft';
    const VOIDED = 'voided';
    private string $status;

    public function __construct(private Estimate $estimate)
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
     * Computes the estimate status.
     */
    private function determine(): string
    {
        if ($this->estimate->voided) {
            return self::VOIDED;
        }

        if ($this->estimate->draft) {
            return self::DRAFT;
        }

        if ($this->estimate->invoice_id) {
            return self::INVOICED;
        }

        if ($this->estimate->approved) {
            return self::APPROVED;
        }

        if ($this->estimate->closed) {
            return self::DECLINED;
        }

        $expires = $this->estimate->expiration_date;
        if ($expires > 0 && $expires < time()) {
            return self::EXPIRED;
        }

        if ($this->estimate->viewed) {
            return self::VIEWED;
        }

        if ($this->estimate->sent) {
            return self::SENT;
        }

        return self::NOT_SENT;
    }
}
