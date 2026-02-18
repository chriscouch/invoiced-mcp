<?php

namespace App\PaymentProcessing\Enums;

use InvalidArgumentException;

enum TokenizationFlowStatus: int
{
    case CollectPaymentDetails = 1;
    case Canceled = 2;
    case ActionRequired = 3;
    case Succeeded = 4;
    case Failed = 5;

    public function toString(): string
    {
        return match ($this) {
            self::CollectPaymentDetails => 'collect_payment_details',
            self::Canceled => 'canceled',
            self::ActionRequired => 'action_required',
            self::Succeeded => 'completed',
            self::Failed => 'failed',
        };
    }

    public static function fromString(string $status): self
    {
        return match ($status) {
            'collect_payment_details' => self::CollectPaymentDetails,
            'canceled' => self::Canceled,
            'action_required' => self::ActionRequired,
            'succeeded' => self::Succeeded,
            'failed' => self::Failed,
            default => throw new InvalidArgumentException("Invalid status: $status"),
        };
    }

    public function isFinalState(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Canceled => true,
            default => false,
        };
    }
}
