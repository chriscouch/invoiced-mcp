<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\Interfaces\AttributedValueInterface;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class PayoutMessage extends BaseMessage
{
    protected function payoutCreated(): array
    {
        return [
            new AttributedString('A payout for '),
            $this->payout(),
            new AttributedString(' was initiated'),
        ];
    }

    private function payout(): AttributedValueInterface
    {
        return new AttributedObject('payout', $this->moneyAmount(), $this->associations['payout'] ?? null);
    }
}
