<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;

trait InvoiceReaderTrait
{
    public static function getDefaultPriority(): int
    {
        return 15;
    }

    public function invoicedObjectType(): ObjectType
    {
        return ObjectType::Invoice;
    }

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->read_invoices;
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Invoices from '.$syncProfile->getIntegrationType()->toHumanName();
    }
}
