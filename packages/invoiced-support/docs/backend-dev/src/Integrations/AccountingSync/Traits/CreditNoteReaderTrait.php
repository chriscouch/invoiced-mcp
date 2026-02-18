<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;

trait CreditNoteReaderTrait
{
    public static function getDefaultPriority(): int
    {
        return 10;
    }

    public function invoicedObjectType(): ObjectType
    {
        return ObjectType::CreditNote;
    }

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->read_credit_notes;
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Credit Notes from '.$syncProfile->getIntegrationType()->toHumanName();
    }
}
