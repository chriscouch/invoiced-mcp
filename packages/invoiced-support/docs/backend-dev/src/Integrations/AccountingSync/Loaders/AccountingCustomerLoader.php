<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Core\Authentication\Models\User;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Traits\AccountingLoaderTrait;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\InvoicedCustomer;

class AccountingCustomerLoader implements LoaderInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    use AccountingLoaderTrait;

    /**
     * @param AccountingCustomer $accountingCustomer
     */
    public function load(AbstractAccountingRecord $accountingCustomer): ImportRecordResult
    {
        $existingCustomer = $this->findExisting($accountingCustomer);

        // If the mapping already exists and Invoiced is the source of the record
        // then we do not proceed with updating the record from the accounting system values.
        if ($existingCustomer && AccountingCustomerMapping::SOURCE_INVOICED == $existingCustomer->mapping?->source) {
            return new ImportRecordResult($existingCustomer->customer);
        }

        if ($existingCustomer) {
            // Delete an existing customer.
            if ($accountingCustomer->deleted) {
                return $this->deleteCustomer($accountingCustomer, $existingCustomer->customer);
            }

            // Update an existing customer
            return $this->updateCustomer($existingCustomer, $accountingCustomer);
        }

        // If the customer does not exist and is already deleted then we do not create it.
        if ($accountingCustomer->deleted) {
            return new ImportRecordResult();
        }

        // Create a new customer if it does not exist
        return $this->createCustomer($accountingCustomer);
    }

    /**
     * Attempts to look up an existing customer using the details from
     * the accounting customer.
     *
     * A customer can be identified by the first match: (in this order)
     * 1. Accounting ID
     * 2. Customer Name
     * 3. Customer Number
     */
    public function findExisting(AccountingCustomer $accountingCustomer): ?InvoicedCustomer
    {
        if ($accountingCustomer->accountingId) {
            $mapping = AccountingCustomerMapping::findForAccountingId($accountingCustomer->integration, $accountingCustomer->accountingId);
        } else {
            $mapping = null;
        }

        $accountNumber = $accountingCustomer->values['number'] ?? null;

        if ($mapping instanceof AccountingCustomerMapping) {
            // Look for an existing customer mapping using the accounting ID
            $customer = $mapping->customer;
        } elseif ($accountNumber) {
            // Look for existing customer by account #, when provided. The account
            // number is a unique identifier that should always be followed if given.
            $customer = Customer::where('number', $accountNumber)->oneOrNull();
        } else {
            // Look for existing customer by name. This is less precise than
            // the other criteria since customer name is not guaranteed to be unique.
            $customer = Customer::where('name', $accountingCustomer->values['name'] ?? '')->oneOrNull();
        }

        return $customer ? new InvoicedCustomer($customer, $mapping) : null;
    }

    /**
     * Creates an Invoiced customer.
     *
     * @throws LoadException if the customer creation fails
     *
     * @return ImportRecordResult<Customer>
     */
    private function createCustomer(AccountingCustomer $accountingCustomer): ImportRecordResult
    {
        $customer = new Customer();
        $emails = $this->populateCustomer($customer, $accountingCustomer);

        if (!$customer->create()) {
            // grab error messages, if creating customer fails
            throw $this->makeException($accountingCustomer, 'Could not create customer: '.$customer->getErrors());
        }

        // create email contacts
        if (is_array($emails)) {
            $this->saveEmailContacts($accountingCustomer, $customer, $emails);
        }

        // create/update contacts
        $this->saveContacts($accountingCustomer, $customer);

        // create a mapping
        $this->saveMapping($customer, $accountingCustomer);

        return $this->makeCreateResult($accountingCustomer, $customer);
    }

    /**
     * Updates an Invoiced customer.
     *
     * @throws LoadException if the customer update fails
     */
    private function updateCustomer(InvoicedCustomer $existingCustomer, AccountingCustomer $accountingCustomer): ImportRecordResult
    {
        $customer = $existingCustomer->customer;
        $emails = $this->populateCustomer($customer, $accountingCustomer);

        if ($customer->dirty() && !$customer->save()) {
            // grab error messages, if updating customer fails
            throw $this->makeException($accountingCustomer, 'Could not update customer: '.$customer->getErrors());
        }

        // create email contacts
        if (is_array($emails)) {
            $this->saveEmailContacts($accountingCustomer, $customer, $emails);
        }

        // create/update contacts
        $this->saveContacts($accountingCustomer, $customer);

        // create or update the mapping
        // the update is important because the accounting ID could have changed
        $mapping = $existingCustomer->mapping ?? AccountingCustomerMapping::find($customer->id());
        $this->saveMapping($customer, $accountingCustomer, $mapping);

        return $this->makeUpdateResult($accountingCustomer, $customer);
    }

    /**
     * Deletes an Invoiced customer.
     *
     * NOTE: This operation will fail if the customer has any transactions.
     *
     * @throws LoadException if the delete fails
     */
    private function deleteCustomer(AccountingCustomer $accountingCustomer, Customer $customer): ImportRecordResult
    {
        if (!$customer->delete()) {
            throw $this->makeException($accountingCustomer, 'Could not delete customer: '.$customer->getErrors());
        }

        return $this->makeDeleteResult($accountingCustomer, $customer);
    }

    private function populateCustomer(Customer $customer, AccountingCustomer $accountingCustomer): ?array
    {
        $values = $accountingCustomer->values;

        // look for parent customer mapping
        if (false !== $accountingCustomer->parentCustomer) {
            $values['parent_customer'] = $this->loadParentCustomer($accountingCustomer->parentCustomer);
        }

        // promote the first email address in the email list to the customer email field
        $emails = $accountingCustomer->emails;
        if (!isset($values['email']) && is_array($emails) && count($emails) > 0) {
            $values['email'] = $emails[0];
            unset($emails[0]);
        }

        foreach ($values as $k => $v) {
            // Make sure that existing metadata not included
            // in the import is not overwritten.
            if ('metadata' === $k) {
                $v = (object) array_filter(array_merge((array) $customer->metadata, (array) $v));
            }

            // Special case for owner property
            if ('owner' == $k) {
                $user = $v ? User::find($v) : null;
                if ($customer->owner?->id != $user?->id) {
                    $customer->owner = $user;
                }
            } elseif ($customer->$k != $v) {
                $customer->$k = $v;
            }
        }

        $customer->skipReconciliation();

        return $emails;
    }

    private function loadParentCustomer(?AccountingCustomer $parent): ?int
    {
        if (!$parent) {
            return null;
        }

        $existing = $this->findExisting($parent);
        if ($existing) {
            return $existing->customer->id;
        }

        try {
            $record = $this->createCustomer($parent)->getModel();
        } catch (LoadException) {
            // we ignore parent customer which we can't create
            return null;
        }

        return $record?->id;
    }

    /**
     * Creates secondary contacts for a customer's email list.
     *
     * @throws LoadException
     */
    private function saveEmailContacts(AccountingCustomer $accountingCustomer, Customer $customer, array $emails): void
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
                throw $this->makeException($accountingCustomer, 'Could not create contact: '.$contact->getErrors());
            }
        }
    }

    /**
     * Creates contacts for a customer.
     *
     * @throws LoadException
     */
    private function saveContacts(AccountingCustomer $accountingCustomer, Customer $customer): void
    {
        if (!is_array($accountingCustomer->contacts)) {
            return;
        }

        $contactIds = [];
        foreach ($accountingCustomer->contacts as $contactValues) {
            // look for an existing contact first by
            // email address and then by name
            if (isset($contactValues['name'])) {
                $contact = Contact::where('customer_id', $customer)
                    ->where('name', $contactValues['name'])
                    ->oneOrNull();
            } elseif (isset($contactValues['email'])) {
                $contact = Contact::where('customer_id', $customer)
                    ->where('email', $contactValues['email'])
                    ->oneOrNull();
            } else {
                $contact = null;
            }

            if (!$contact) {
                $contact = new Contact();
                $contact->customer = $customer;
            }

            foreach ($contactValues as $k => $v) {
                $contact->$k = $v;
            }

            if (!$contact->save()) {
                // grab error messages, if creating contact fails
                throw $this->makeException($accountingCustomer, 'Could not create contact: '.$contact->getErrors());
            }

            $contactIds[] = $contact->id();
        }

        // delete any contacts that were not just created or updated
        $query = Contact::where('customer_id', $customer);
        if (count($contactIds) > 0) {
            $query->where('id NOT IN ('.implode(',', $contactIds).')');
        }
        $query->delete();
    }

    private function saveMapping(Customer $customer, AccountingCustomer $accountingCustomer, ?AccountingCustomerMapping $mapping = null): void
    {
        // Create a new mapping if one does not already exist. When creating
        // a new mapping here the record source is implied to be the accounting system.
        if (!$mapping) {
            $mapping = new AccountingCustomerMapping();
            $mapping->customer = $customer;
            $mapping->source = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM;
        }

        if ($mapping->integration_id != $accountingCustomer->integration->value) {
            $mapping->setIntegration($accountingCustomer->integration);
        }

        if ($mapping->accounting_id != $accountingCustomer->accountingId) {
            $mapping->accounting_id = $accountingCustomer->accountingId;
        }

        if ($mapping->dirty()) {
            $mapping->save();
        }
    }
}
