<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\Enums\IntegrationType;

final readonly class WriteRetryContext extends RetryContext
{
    public function __construct(IntegrationType $integrationType, string $modelClass, string $modelId, string $eventName, int $errorId)
    {
        $data = [
            'id' => $modelId,
            'class' => $modelClass,
            'eventName' => $eventName,
            'accounting_system' => $integrationType->value,
        ];

        parent::__construct(false, $data, $errorId);
    }
}
