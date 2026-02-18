<?php

namespace App\Chasing\Enums;

use App\Chasing\Models\ChasingCadenceStep;
use App\Sending\Models\ScheduledSend;

enum ChasingChannelEnum: int
{
    case None = 0;
    case Text = 1;
    case Letter = 2;
    case Email = 4;
    case Phone = 8;
    case Escalate = 16;

    public static function fromChasingCadenceStep(ChasingCadenceStep $step): self
    {
        return match ($step->action) {
            ChasingCadenceStep::ACTION_EMAIL => self::Email,
            ChasingCadenceStep::ACTION_SMS => self::Text,
            ChasingCadenceStep::ACTION_PHONE => self::Phone,
            ChasingCadenceStep::ACTION_MAIL => self::Letter,
            ChasingCadenceStep::ACTION_ESCALATE => self::Escalate,
            default => self::None,
        };
    }

    public static function fromScheduledSend(ScheduledSend $send): self
    {
        return match ($send->getChannel()) {
            ScheduledSend::EMAIL_CHANNEL_STR => self::Email,
            ScheduledSend::SMS_CHANNEL_STR => self::Text,
            ScheduledSend::LETTER_CHANNEL_STR => self::Letter,
            default => self::None,
        };
    }
}
