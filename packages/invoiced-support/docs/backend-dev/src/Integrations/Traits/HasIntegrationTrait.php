<?php

namespace App\Integrations\Traits;

use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int $integration_id
 */
trait HasIntegrationTrait
{
    protected static function autoDefinitionIntegration(): array
    {
        return [
            'integration_id' => new Property(
                type: Type::INTEGER,
                required: true,
                in_array: false,
            ),
        ];
    }

    public function getIntegrationType(): IntegrationType
    {
        return IntegrationType::from($this->integration_id);
    }

    public function setIntegration(IntegrationType $integrationType): void
    {
        $this->integration_id = $integrationType->value;
    }
}
