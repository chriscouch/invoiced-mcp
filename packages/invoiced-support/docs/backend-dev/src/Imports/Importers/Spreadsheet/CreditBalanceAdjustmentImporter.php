<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;

class CreditBalanceAdjustmentImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;

    protected function findExistingRecord(array $record): ?Model
    {
        return null;
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $adjustment = new CreditBalanceAdjustment();
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $adjustment->setCustomer($customer);
            }
            unset($record['customer']);
        }
        if (!$adjustment->create($record)) {
            throw new RecordException('Could not create credit balance adjustment: '.$adjustment->getErrors());
        }

        return new ImportRecordResult($adjustment, ImportRecordResult::CREATE);
    }
}
