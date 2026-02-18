<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;

trait CustomerReaderTrait
{
    public static function getDefaultPriority(): int
    {
        return 20;
    }

    public function invoicedObjectType(): ObjectType
    {
        return ObjectType::Customer;
    }

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->read_customers;
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Customers from '.$syncProfile->getIntegrationType()->toHumanName();
    }
}
