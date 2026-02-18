<?php

namespace App\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Enums\ChasingChannelEnum;
use App\Chasing\Enums\ChasingTypeEnum;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use Carbon\CarbonImmutable;

/**
 * @property CarbonImmutable|string      $date
 * @property int                         $type
 * @property ?int                        $customer_id
 * @property ?int                        $cadence_id
 * @property ?int                        $invoice_cadence_id
 * @property ?int                        $cadence_step_id
 * @property int                         $channel
 * @property int                         $invoice_id
 * @property int                         $attempts
 * @property CarbonImmutable|string|null $paid
 * @property ?bool                       $payment_responsible
 */
class ChasingStatistic extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'date' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'type' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [self::class, 'ofType']],
            ),
            'customer_id' => new Property(
                type: Type::INTEGER,
                relation: Customer::class,
            ),
            'cadence_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                relation: ChasingCadence::class,
            ),
            'invoice_cadence_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                relation: InvoiceChasingCadence::class,
            ),
            'cadence_step_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
            ),
            'channel' => new Property(
                type: Type::INTEGER,
                validate: ['callable', 'fn' => [self::class, 'ofChannel']],
            ),
            'invoice_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'paid' => new Property(
                type: Type::STRING,
            ),
            'payment_responsible' => new Property(
                type: Type::BOOLEAN,
            ),
            // first attempt results in the attempts count 1
            'attempts' => new Property(
                type: Type::INTEGER,
                default: 1,
            ),
        ];
    }

    public static function ofType(int $value): bool
    {
        return (bool) ChasingTypeEnum::tryFrom($value);
    }

    public static function ofChannel(int $value): bool
    {
        return (bool) ChasingChannelEnum::tryFrom($value);
    }
}
