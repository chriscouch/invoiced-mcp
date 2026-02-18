<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Traits\DeleteOperationTrait;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;

class ContactImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;
    use DeleteOperationTrait;

    protected function findExistingRecord(array $record): ?Model
    {
        // Contacts are identified by customer AND contact name
        if (!isset($record['customer'])) {
            return null;
        }

        $customer = $this->getCustomerObject($record['customer']);
        if (!($customer instanceof Customer)) {
            return null;
        }

        return Contact::where('customer_id', $customer)
            ->where('name', array_value($record, 'name'))
            ->oneOrNull();
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $contact = new Contact();
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $contact->customer = $customer;
            }
            unset($record['customer']);
        }
        if (!$contact->create($record)) {
            throw new RecordException('Could not create contact: '.$contact->getErrors());
        }

        return new ImportRecordResult($contact, ImportRecordResult::CREATE);
    }

    /**
     * @param Contact $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        if (isset($record['customer'])) {
            unset($record['customer']);
        }
        foreach ($record as $k => $v) {
            $existingRecord->$k = $v;
        }

        if (!$existingRecord->save()) {
            throw new RecordException('Could not update contact: '.$existingRecord->getErrors());
        }

        return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
    }
}
