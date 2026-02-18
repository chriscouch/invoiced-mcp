<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;

trait PaymentReaderTrait
{
    public static function getDefaultPriority(): int
    {
        return 5;
    }

    public function invoicedObjectType(): ObjectType
    {
        return ObjectType::Payment;
    }

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->read_payments;
    }

    protected function getDisplayName(AccountingSyncProfile $syncProfile): string
    {
        return 'Payments from '.$syncProfile->getIntegrationType()->toHumanName();
    }
}
