<?php

namespace App\ActivityLog\Libs\Messages;

use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Core\I18n\MoneyFormatter;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class EstimateMessage extends BaseMessage
{
    private static array $statuses = [
        EstimateStatus::INVOICED => 'Invoiced',
        EstimateStatus::DECLINED => 'Declined',
        EstimateStatus::APPROVED => 'Approved',
        EstimateStatus::EXPIRED => 'Expired',
        EstimateStatus::VIEWED => 'Viewed',
        EstimateStatus::SENT => 'Sent',
        EstimateStatus::NOT_SENT => 'Not Sent',
        EstimateStatus::DRAFT => 'Draft',
        EstimateStatus::VOIDED => 'Voided',
    ];

    protected function estimateCreated(): array
    {
        $newStr = ' was issued for ';
        if (array_value($this->object, 'draft')) {
            $newStr = ' was drafted for ';
        }

        return [
            new AttributedString('An estimate for '),
            new AttributedObject('estimate', $this->moneyAmount(), array_value($this->associations, 'estimate')),
            new AttributedString($newStr),
            $this->customer('customerName'),
        ];
    }

    protected function estimateUpdated(): array
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
            $pastStatus = array_value(self::$statuses, $this->previous['status']);
            $newStatus = array_value(self::$statuses, $this->object['status']);
            $updateStr = " went from \"$pastStatus\" to \"$newStatus\"";

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

            // closed
        } elseif (isset($this->previous['closed']) && !$this->previous['closed']) {
            $updateStr = ' was closed';

            // reopened
        } elseif (isset($this->previous['closed']) && $this->previous['closed']) {
            $updateStr = ' was reopened';
        }

        return [
            $this->estimate(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString($updateStr),
        ];
    }

    protected function estimateApproved(): array
    {
        $initials = array_value($this->object, 'approved');
        if (!$initials) {
            $initials = array_value($this->object, 'initials');
        }

        return [
            $this->estimate(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString(' was approved by '.$initials),
        ];
    }

    protected function estimateDeleted(): array
    {
        return [
            $this->estimate(),
            new AttributedString(' for '),
            $this->customer('customerName'),
            new AttributedString(' was removed'),
        ];
    }
}
