<?php

namespace App\PaymentProcessing\Enums;

use InvalidArgumentException;

enum PaymentFlowStatus: int
{
    case CollectPaymentDetails = 1;
    case Canceled = 2;
    case ActionRequired = 3;
    case Processing = 4;
    case Succeeded = 5;
    case Failed = 6;

    public function toString(): string
    {
        return match ($this) {
            self::CollectPaymentDetails => 'collect_payment_details',
            self::Canceled => 'canceled',
            self::ActionRequired => 'action_required',
            self::Processing => 'processing',
            self::Succeeded => 'succeeded',
            self::Failed => 'failed',
        };
    }

    public static function fromString(string $status): self
    {
        return match ($status) {
            'collect_payment_details' => self::CollectPaymentDetails,
            'canceled' => self::Canceled,
            'action_required' => self::ActionRequired,
            'processing' => self::Processing,
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
