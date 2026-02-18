<?php

namespace App\Integrations\AccountingSync;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\ValueObjects\ReadRetryContext;
use App\Integrations\AccountingSync\ValueObjects\RetryContext;
use App\Integrations\AccountingSync\ValueObjects\WriteRetryContext;
use App\Integrations\Enums\IntegrationType;
use RuntimeException;

class RetryContextFactory
{
    public function make(ReconciliationError $error): ?RetryContext
    {
        $retryData = $error->retry_context;

        // This represents an operation posting from
        // the accounting system to Invoiced
        $integrationType = $error->getIntegrationType();
        if ($error->accounting_id) {
            if (IntegrationType::QuickBooksDesktop === $integrationType) {
                return null;
            }

            return new ReadRetryContext((array) $retryData, (int) $error->id());
        }

        // This represents an operation posting from
        // Invoiced to the accounting system
        $objectId = $error->object_id;
        if ($objectId && property_exists($retryData, 'e')) {
            try {
                $type = ObjectType::fromTypeName($error->object);
            } catch (RuntimeException) {
                // if the type is not recognized then it cannot be retried
                return null;
            }

            $event = $retryData->e;

            return new WriteRetryContext($integrationType, $type->modelClass(), (string) $objectId, $event, (int) $error->id());
        }

        return null;
    }
}
