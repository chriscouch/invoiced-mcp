<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Traits\HasIntegrationTrait;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property DateTimeInterface|null $started_at
 * @property DateTimeInterface|null $finished_at
 * @property DateTimeInterface|null $last_updated_at
 * @property string|null            $message
 * @property string|null            $query
 * @property bool                   $running
 */
class AccountingSyncStatus extends MultitenantModel
{
    use HasIntegrationTrait;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'started_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'finished_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'last_updated_at' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'query' => new Property(
                null: true,
            ),
            'message' => new Property(
                null: true,
            ),
            'running' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    public static function beingSync(IntegrationType $integrationType, ReadQuery $query): void
    {
        $status = self::getInstance($integrationType);
        $status->running = true;
        $status->query = (string) json_encode($query);
        $status->started_at = CarbonImmutable::now();
        $status->last_updated_at = CarbonImmutable::now();
        $status->message = null;
        $status->finished_at = null;
        $status->save();
    }

    public static function setMessage(IntegrationType $integrationType, string $message): void
    {
        $status = self::getInstance($integrationType);
        $status->last_updated_at = CarbonImmutable::now();
        $status->message = $message;
        $status->save();
    }

    public static function finishSync(IntegrationType $integrationType): void
    {
        $status = self::getInstance($integrationType);
        $status->running = false;
        $status->last_updated_at = CarbonImmutable::now();
        $status->message = null;
        $status->finished_at = CarbonImmutable::now();
        $status->save();
    }

    private static function getInstance(IntegrationType $integrationType): self
    {
        $status = self::where('integration_id', $integrationType->value)->oneOrNull();
        if (!$status) {
            $status = new AccountingSyncStatus();
            $status->setIntegration($integrationType);
        }

        return $status;
    }
}
