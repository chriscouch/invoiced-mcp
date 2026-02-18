<?php

namespace App\ActivityLog\Libs\Messages;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\Core\I18n\MoneyFormatter;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use ICanBoogie\Inflector;

class InvoiceMessage extends BaseMessage
{
    private static array $statuses = [
        InvoiceStatus::Paid->value => 'Paid',
        InvoiceStatus::BadDebt->value => 'Bad Debt',
        InvoiceStatus::PastDue->value => 'Past Due',
        InvoiceStatus::Viewed->value => 'Viewed',
        InvoiceStatus::Sent->value => 'Sent',
        InvoiceStatus::NotSent->value => 'Not Sent',
        InvoiceStatus::Draft->value => 'Draft',
        InvoiceStatus::Pending->value => 'Pending',
        InvoiceStatus::Voided->value => 'Voided',
    ];

    protected function invoiceCreated(): array
    {
        $newStr = ' was invoiced for ';
        if (array_value($this->object, 'draft')) {
            $newStr = ' was drafted an invoice for ';
        }

        return [
            $this->customer('customerName'),
            new AttributedString($newStr),
            new AttributedObject('invoice', $this->moneyAmount(), array_value($this->associations, 'invoice')),
        ];
    }

    protected function invoiceUpdated(): array
    {
        $updateStr = ' was updated';

        // marked paid
        if (isset($this->previous['paid']) && !$this->previous['paid']) {
            $updateStr = ' was marked paid';

            // marked sent
        } elseif (isset($this->previous['sent']) && !$this->previous['sent']) {
            $updateStr = ' was marked sent';

            // issued
        } elseif (isset($this->previous['draft']) && $this->previous['draft']) {
            $updateStr = ' was issued';

            // autopay enabled / disabled
        } elseif (isset($this->previous['autopay'])) {
            $updateStr = ' had AutoPay '.((array_value($this->object, 'autopay')) ? 'enabled' : 'disabled');

            // flagged as needs attention
        } elseif (isset($this->previous['needs_attention']) && !$this->previous['needs_attention']) {
            $updateStr = ' was flagged';

            // marked resolved
        } elseif (isset($this->previous['needs_attention']) && $this->previous['needs_attention']) {
            $updateStr = ' was marked as resolved';

            // chasing enabled / disabled
        } elseif (isset($this->previous['chase'])) {
            $updateStr = ' had chasing '.((array_value($this->object, 'chase')) ? 'enabled' : 'disabled');

            // chasing
        } elseif (array_key_exists('next_chase_on', $this->previous)) {
            $next = array_value($this->object, 'next_chase_on');
            if ($next) {
                $updateStr = ' was scheduled for chasing on '.date('M j, Y', $next);
            } else {
                $updateStr = ' has no further chasing attempts scheduled';
            }

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

            // payment attempt
        } elseif (isset($this->previous['attempt_count'])) {
            $updateStr = ' had a payment attempt';

            // closed
        } elseif (isset($this->previous['closed']) && !$this->previous['closed']) {
            $updateStr = ' was closed';

            // reopened
        } elseif (isset($this->previous['closed']) && $this->previous['closed']) {
            $updateStr = ' was reopened';
        }

        return [
            $this->invoice(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString($updateStr),
        ];
    }

    protected function invoiceDeleted(): array
    {
        return [
            $this->invoice(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString(' was removed'),
        ];
    }

    protected function invoicePaid(): array
    {
        return [
            $this->invoice(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString(' was paid in full'),
        ];
    }

    protected function invoicePaymentExpected(): array
    {
        $date = date('M j, Y', array_value($this->object, 'date'));
        $inflector = Inflector::get();
        $method = strtolower($inflector->humanize(array_value($this->object, 'method')));

        return [
            $this->customer(),
            new AttributedString(" expects payment to arrive by $date via $method for "),
            $this->invoice(),
        ];
    }

    protected function invoiceFollowUpNoteCreated(): array
    {
        return [
            new AttributedString('Follow up note recorded for '),
            $this->invoice(),
        ];
    }
}
