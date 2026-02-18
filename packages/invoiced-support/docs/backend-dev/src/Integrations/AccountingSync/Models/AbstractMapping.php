<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Traits\HasIntegrationTrait;
use App\Core\Orm\Exception\DriverException;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string $accounting_id
 * @property string $source
 */
abstract class AbstractMapping extends MultitenantModel
{
    use AutoTimestamps;
    use HasIntegrationTrait;

    const SOURCE_ACCOUNTING_SYSTEM = 'accounting_system';
    const SOURCE_INVOICED = 'invoiced';

    public function create(array $data = []): bool
    {
        try {
            return parent::create($data);
        } catch (DriverException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return true;
            }

            throw $e;
        }
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['integration_name'] = $this->getIntegrationType()->toHumanName();
        $result['updated_at'] = $this->updated_at;

        return $result;
    }

    public static function findForAccountingId(IntegrationType $integration, string $id): ?static
    {
        return static::where('integration_id', $integration->value)
            ->where('accounting_id', $id)
            ->oneOrNull();
    }
}
