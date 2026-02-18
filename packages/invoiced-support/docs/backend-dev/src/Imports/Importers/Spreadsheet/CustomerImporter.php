<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\Authentication\Models\User;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\Traits\DeleteOperationTrait;
use App\Imports\Traits\ImportAccountingParametersTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * Imports customers from a spreadsheet.
 */
class CustomerImporter extends BaseSpreadsheetImporter
{
    use DeleteOperationTrait;
    use ImportAccountingParametersTrait;

    /** @var (User|null)[] */
    private array $owners = [];
    /** @var (ChasingCadence|null)[] */
    private array $chasingCadences = [];
    /** @var (LateFeeSchedule|null)[] */
    private array $lateFeeSchedules = [];
    /** @var (MerchantAccount|null)[] */
    private array $merchantAccounts = [];

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        // clear out an empty account number
        if (isset($record['number']) && !$record['number']) {
            unset($record['number']);
        }

        // convert country long form name to abbreviation (i.e. United States -> US)
        if (isset($record['country'])) {
            $record['country'] = ImportHelper::parseCountry($record['country']);
        }

        // convert state long from name to abbreviation (i.e. Texas -> TX)
        if (isset($record['state'])) {
            $country = $record['country'] ?? '';
            $country = $country ?: $import->tenant()->country;
            $record['state'] = ImportHelper::parseState($record['state'], $country);
        }

        $record['emails'] = [];
        if (isset($record['email'])) {
            $record['emails'] = ImportHelper::parseEmailAddress($record['email']);
            unset($record['email']);
        }

        // set ach gateway to MerchantAccount id
        if (isset($record['ach_gateway']) && !empty($record['ach_gateway'])) {
            $record['ach_gateway'] = $this->getMerchantAccount($record['ach_gateway']);
        }

        // set cc gateway to MerchantAccount id
        if (isset($record['cc_gateway']) && !empty($record['cc_gateway'])) {
            $record['cc_gateway'] = $this->getMerchantAccount($record['cc_gateway']);
        }

        // ignore active status if null
        if (array_key_exists('active', $record) && null === $record['active']) {
            unset($record['active']);
        }

        // ensure type is lowercase
        if (isset($record['type'])) {
            $record['type'] = strtolower($record['type']);
        }

        // ensure taxes is an array
        if (array_key_exists('taxes', $record)) {
            $rates = explode(',', (string) $record['taxes']);
            $record['taxes'] = array_filter($rates);
        }

        // look up the owner by email
        if (isset($record['owner']) && $record['owner']) {
            $owner = $this->getOwner($record['owner']);
            if (!$owner) {
                throw new ValidationException('Unrecognized owner email address: '.$record['owner']);
            }

            $record['owner'] = $owner;
        }

        // look up the chasing cadence by name
        if (isset($record['chasing_cadence'])) {
            if ($record['chasing_cadence']) {
                $cadence = $this->getCadence($record['chasing_cadence']);
                if (!$cadence) {
                    throw new ValidationException('Unrecognized cadence: '.$record['chasing_cadence']);
                }

                $record['chasing_cadence'] = $cadence->id();

                if (isset($record['next_chase_step'])) {
                    if ($record['next_chase_step']) {
                        $step = $this->getNextStep($cadence, $record['next_chase_step']);
                        if (!$step) {
                            throw new ValidationException('Unrecognized step for "'.$cadence->name.'" cadence: '.$record['next_chase_step']);
                        }

                        $record['next_chase_step'] = $step->id();
                    } else {
                        $record['next_chase_step'] = null;
                    }
                }
            } else {
                // When the cadence is being cleared out we have to
                // disable chasing or else the assigner will assign
                // a new cadence automatically.
                $record['chase'] = false;
                $record['chasing_cadence'] = null;
                $record['next_chase_step'] = null;
            }
        } elseif (isset($record['next_chase_step'])) {
            throw new ValidationException('Cannot change the next chasing step without specifying a cadence.');
        }

        if (isset($record['late_fee_schedule'])) {
            if ($record['late_fee_schedule']) {
                $schedule = $this->getLateFeeSchedule($record['late_fee_schedule']);
                if (!$schedule) {
                    throw new ValidationException('Unrecognized late fee schedule: '.$record['late_fee_schedule']);
                }

                $record['late_fee_schedule'] = $schedule;
            } else {
                $record['late_fee_schedule'] = null;
            }
        }

        return $this->buildRecordAccounting($record);
    }

    //
    // Operations
    //

    protected function findExistingRecord(array $record): ?Model
    {
        // Customers are identified by account #, when provided.
        // If not provided then customers are identified by name.
        if ($accountNumber = array_value($record, 'number')) {
            return Customer::where('number', $accountNumber)->oneOrNull();
        }

        return Customer::where('name', array_value($record, 'name'))->oneOrNull();
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        // parse out email addresses
        $emails = $record['emails'];
        unset($record['emails']);
        if (count($emails) > 0) {
            $record['email'] = $emails[0];
        }

        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        if (isset($record['owner']) && $record['owner'] === ''){
            $record['owner'] = null;
        }

        $customer = new Customer();

        // Imported customers are not reconciled to the accounting system.
        // We might later want to make this a setting.
        $customer->skipReconciliation();

        if (!$customer->create($record)) {
            // grab error messages, if creating customer fails
            throw new RecordException('Could not create customer: '.$customer->getErrors());
        }

        // create secondary contacts
        if (count($emails) > 1) {
            $this->importContacts($customer, array_slice($emails, 1));
        }

        // save accounting mapping
        if ($accountingSystem && $accountingId) {
            $this->saveAccountingMapping($customer, $accountingSystem, $accountingId);
        }

        return new ImportRecordResult($customer, ImportRecordResult::CREATE);
    }

    /**
     * @param Customer $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        // parse out email addresses
        $emails = $record['emails'];
        unset($record['emails']);
        if (count($emails) > 0) {
            $record['email'] = $emails[0];
        }

        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        foreach ($record as $k => $v) {
            $this->updateExistingRecord($existingRecord, $k, $v);
        }

        // Imported customers are not reconciled to the accounting system.
        // We might later want to make this a setting.
        $existingRecord->skipReconciliation();

        if (!$existingRecord->save()) {
            // grab error messages, if updating customer fails
            throw new RecordException('Could not update customer: '.$existingRecord->getErrors());
        }

        // create secondary contacts
        if (count($emails) > 1) {
            $this->importContacts($existingRecord, array_slice($emails, 1));
        }

        // save accounting mapping
        if ($accountingSystem && $accountingId) {
            $this->saveAccountingMapping($existingRecord, $accountingSystem, $accountingId);
        }

        return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
    }

    //
    // Helpers
    //

    /**
     * Creates secondary contacts for a customer.
     *
     * @throws RecordException
     */
    private function importContacts(Customer $customer, array $emails): void
    {
        foreach ($emails as $email) {
            // avoid duplicates
            $found = Contact::where('customer_id', $customer->id())
                ->where('email', $email)
                ->count();
            if ($found > 0) {
                continue;
            }

            $contact = new Contact();
            $contact->customer = $customer;
            $contact->name = $customer->name;
            $contact->email = $email;
            if (!$contact->save()) {
                // grab error messages, if creating contact fails
                throw new RecordException('Could not create contact: '.$contact->getErrors());
            }
        }
    }

    private function getOwner(string $email): ?User
    {
        if (!$email) {
            return null;
        }

        if (!array_key_exists($email, $this->owners)) {
            $user = User::where('email', $email)->oneOrNull();
            $this->owners[$email] = $user;
        }

        return $this->owners[$email];
    }

    private function getCadence(string $name): ?ChasingCadence
    {
        if (!$name) {
            return null;
        }

        if (!array_key_exists($name, $this->chasingCadences)) {
            $cadence = ChasingCadence::where('name', $name)->oneOrNull();
            $this->chasingCadences[$name] = $cadence;
        }

        return $this->chasingCadences[$name];
    }

    private function getLateFeeSchedule(string $name): ?LateFeeSchedule
    {
        if (!$name) {
            return null;
        }

        if (!array_key_exists($name, $this->lateFeeSchedules)) {
            $cadence = LateFeeSchedule::where('name', $name)->oneOrNull();
            $this->lateFeeSchedules[$name] = $cadence;
        }

        return $this->lateFeeSchedules[$name];
    }

    private function getNextStep(ChasingCadence $cadence, string $stepName): ?ChasingCadenceStep
    {
        foreach ($cadence->getSteps() as $step) {
            if ($step->name == $stepName) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Obtains a MerchantAccount by name.
     *
     * @throws ValidationException
     */
    private function getMerchantAccount(string $name): MerchantAccount
    {
        $name = trim($name);
        if (isset($this->merchantAccounts[$name])) {
            return $this->merchantAccounts[$name];
        }

        /** @var MerchantAccount|null $merchantAccount */
        $merchantAccount = MerchantAccount::where('name', $name)->oneOrNull() ?? MerchantAccount::where('gateway_id', $name)->oneOrNull();
        if (!$merchantAccount) {
            throw new ValidationException('Gateway not found: "'.$name.'"');
        }

        $this->merchantAccounts[$name] = $merchantAccount;

        return $merchantAccount;
    }
}
