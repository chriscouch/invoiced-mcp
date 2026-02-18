<?php

namespace App\ActivityLog\Libs\Messages;

use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\Core\I18n\MoneyFormatter;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class CreditNoteMessage extends BaseMessage
{
    private static array $statuses = [
        CreditNoteStatus::OPEN => 'Open',
        CreditNoteStatus::CLOSED => 'Closed',
        CreditNoteStatus::PAID => 'Paid',
        CreditNoteStatus::DRAFT => 'Draft',
        CreditNoteStatus::VOIDED => 'Voided',
    ];

    protected function creditNoteCreated(): array
    {
        $newStr = ' was issued for ';
        if (array_value($this->object, 'draft')) {
            $newStr = ' was drafted for ';
        }

        return [
            new AttributedString('A credit note for '),
            new AttributedObject('credit_note', $this->moneyAmount(), array_value($this->associations, 'credit_note')),
            new AttributedString($newStr),
            $this->customer('customerName'),
        ];
    }

    protected function creditNoteUpdated(): array
    {
        $updateStr = ' was updated';

        // marked sent
        if (isset($this->previous['sent']) && !$this->previous['sent']) {
            $updateStr = ' was marked sent';

            // issued
        } elseif (isset($this->previous['draft']) && $this->previous['draft']) {
            $updateStr = ' was issued';

            // status changed
        } elseif (isset($this->previous['status']) && isset($this->object['status'])) {
            $old = array_value(self::$statuses, $this->previous['status']);
            $new = array_value(self::$statuses, $this->object['status']);
            $updateStr = " went from \"$old\" to \"$new\"";

            // total changed
        } elseif (isset($this->previous['total']) && isset($this->object['total'])) {
            $formatter = MoneyFormatter::get();
            $old = $formatter->currencyFormat(
                $this->previous['total'],
                array_value($this->object, 'currency'),
                $this->company->moneyFormat()
            );
            $new = $formatter->currencyFormat(
                $this->object['total'],
                array_value($this->object, 'currency'),
                $this->company->moneyFormat()
            );
            $updateStr = " had its total changed from $old to $new";

            // balance changed
        } elseif (isset($this->previous['balance']) && isset($this->object['balance'])) {
            $formatter = MoneyFormatter::get();
            $old = $formatter->currencyFormat(
                $this->previous['balance'],
                array_value($this->object, 'currency'),
                $this->company->moneyFormat()
            );
            $new = $formatter->currencyFormat(
                $this->object['balance'],
                array_value($this->object, 'currency'),
                $this->company->moneyFormat()
            );
            $updateStr = " had its balance changed from $old to $new";

            // closed
        } elseif (isset($this->previous['closed']) && !$this->previous['closed']) {
            $updateStr = ' was closed';

            // reopened
        } elseif (isset($this->previous['closed']) && $this->previous['closed']) {
            $updateStr = ' was reopened';
        }

        return [
            $this->creditNote(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString($updateStr),
        ];
    }

    protected function creditNoteDeleted(): array
    {
        return [
            $this->creditNote(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString(' was removed'),
        ];
    }
}
