<?php

namespace App\Integrations\Adyen\Enums;

use App\PaymentProcessing\Enums\DisputeStatus;

enum ChargebackEvent: string
{
    case CHARGEBACK = 'CHARGEBACK';
    case CHARGEBACK_REVERSED = 'CHARGEBACK_REVERSED';
    case DISPUTE_DEFENSE_PERIOD_ENDED = 'DISPUTE_DEFENSE_PERIOD_ENDED';
    case DISPUTE_OPENED_WITH_CHARGEBACK = 'DISPUTE_OPENED_WITH_CHARGEBACK';
    case NOTIFICATION_OF_CHARGEBACK = 'NOTIFICATION_OF_CHARGEBACK';
    case PREARBITRATION_LOST = 'PREARBITRATION_LOST';
    case PREARBITRATION_WON = 'PREARBITRATION_WON';
    case REQUEST_FOR_INFORMATION = 'REQUEST_FOR_INFORMATION';
    case SECOND_CHARGEBACK = 'SECOND_CHARGEBACK';
    case INFORMATION_SUPPLIED = 'INFORMATION_SUPPLIED';

    public function toDisputeStatus(): DisputeStatus
    {
        return match ($this) {
            self::REQUEST_FOR_INFORMATION, self::CHARGEBACK, self::NOTIFICATION_OF_CHARGEBACK => DisputeStatus::Unresponded,
            self::SECOND_CHARGEBACK, self::PREARBITRATION_LOST => DisputeStatus::Lost,
            self::PREARBITRATION_WON => DisputeStatus::Won,
            self::CHARGEBACK_REVERSED => DisputeStatus::Pending,
            self::DISPUTE_OPENED_WITH_CHARGEBACK, self::INFORMATION_SUPPLIED => DisputeStatus::Responded,
            self::DISPUTE_DEFENSE_PERIOD_ENDED => DisputeStatus::Expired,
        };
    }

    public function expectedFees(): int
    {
        return match ($this) {
            self::REQUEST_FOR_INFORMATION, self::CHARGEBACK_REVERSED => 0,
            self::SECOND_CHARGEBACK => 2,
            default => 1,
        };
    }

    public static function fromReportRowStatus(string $status): ?self
    {
        return match (strtolower($status)) {
            'chargeback' => self::CHARGEBACK,
            'chargebackreversed' => self::CHARGEBACK_REVERSED,
            'disputedefenseperiodended' => self::DISPUTE_DEFENSE_PERIOD_ENDED,
            'disputeopenedwithchargeback' => self::DISPUTE_OPENED_WITH_CHARGEBACK,
            'notificationofchargeback' => self::NOTIFICATION_OF_CHARGEBACK,
            'prearbitrationlost' => self::PREARBITRATION_LOST,
            'prearbitrationwon' => self::PREARBITRATION_WON,
            'requestforinformation' => self::REQUEST_FOR_INFORMATION,
            'secondchargeback' => self::SECOND_CHARGEBACK,
            'informationsupplied' => self::INFORMATION_SUPPLIED,
            default => null,
        };
    }
}
