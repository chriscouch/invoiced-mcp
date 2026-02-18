<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;

trait ItemReaderTrait
{
    public static function getDefaultPriority(): int
    {
        return 20;
    }

    public function invoicedObjectType(): ObjectType
    {
        return ObjectType::Item;
    }

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        // Items are currently not supported in the ongoing sync.
        return false;
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Items from '.$syncProfile->getIntegrationType()->toHumanName();
    }
}
