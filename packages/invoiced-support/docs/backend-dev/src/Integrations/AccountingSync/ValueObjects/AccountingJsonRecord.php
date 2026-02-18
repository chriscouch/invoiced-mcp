<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use stdClass;

/**
 * This represents a record extracted from the accounting system
 * that has not been transformed into an Invoiced record yet.
 */
final readonly class AccountingJsonRecord implements AccountingRecordInterface
{
    public object $supportingData;

    public function __construct(
        public object $document,
        ?object $supportingData = null,
    ) {
        $this->supportingData = $supportingData ?? new stdClass();
    }
}
