<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;

/**
 * Loads records into Invoiced that have been extracted
 * and transformed from an accounting system integration.
 */
interface LoaderInterface
{
    /**
     * Loads an accounting record into Invoiced that has been extracted
     * and transformed by the accounting system integration.
     *
     * @throws LoadException
     */
    public function load(AbstractAccountingRecord $record): ImportRecordResult;
}
