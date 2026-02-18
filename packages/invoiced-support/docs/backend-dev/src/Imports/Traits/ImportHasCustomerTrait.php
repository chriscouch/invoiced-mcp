<?php

namespace App\Imports\Traits;

use App\AccountsReceivable\Models\Customer;

trait ImportHasCustomerTrait
{
    /** @var Customer[] */
    private array $customersByNumber = [];
    /** @var Customer[] */
    private array $customersByName = [];

    /**
     * Looks up or creates a customer given an array of attributes.
     *
     * @param array $customer built in ImportHelper::mapCustomerProfile()
     */
    protected function getCustomerObject(array $customer): ?Customer
    {
        // lookup using the customer ID, if given
        if ($id = array_value($customer, 'id')) {
            return Customer::find($id);
        }

        // lookup using the account number, if given
        if ($accountNumber = array_value($customer, 'number')) {
            return $this->getCustomerFromNumber($accountNumber, $customer);
        }

        // look up the customer by name, if given
        if ($name = array_value($customer, 'name')) {
            return $this->getCustomerFromName($name, $customer);
        }

        // if none of these values are available then the customer
        // does not exist
        return null;
    }

    /**
     * Gets a customer from a given customer number.
     *
     * @param array $profile properties that will be used to create customer if necessary
     */
    private function getCustomerFromNumber(string $accountNumber, array $profile = []): ?Customer
    {
        if (empty($accountNumber)) {
            return null;
        }

        // Possible scenarios:
        // i) created in this import
        // ii) already created
        // iii) need to create the customer (if sufficient information is available)

        // i. check if customer was created in this import
        if (isset($this->customersByNumber[$accountNumber])) {
            return $this->customersByNumber[$accountNumber];
        }

        // ii. lookup the customer in the db
        if ($customer = Customer::where('number', $accountNumber)->oneOrNull()) {
            $custName = strtolower(trim($customer->name));
            $this->customersByNumber[$accountNumber] = $customer;
            $this->customersByName[$custName] = $customer;

            return $customer;
        }

        // iii. create the customer
        $customer = new Customer();
        if ($customer->create($profile)) {
            $custName = strtolower(trim($customer->name));
            $this->customersByNumber[$accountNumber] = $customer;
            $this->customersByName[$custName] = $customer;

            return $customer;
        }

        return null;
    }

    /**
     * Gets a customer from a given customer name.
     *
     * @param array $profile properties that will be used to create customer if necessary
     */
    private function getCustomerFromName(string $customerName, array $profile = []): ?Customer
    {
        if (empty($customerName)) {
            return null;
        }

        $customerName = strtolower(trim($customerName));

        // Possible scenarios:
        // i) created in this import
        // ii) already created (matched by lowercase name comparison)
        // iii) need to create the customer

        // i. check if customer was created in this import
        if (isset($this->customersByName[$customerName])) {
            return $this->customersByName[$customerName];

            // ii. lookup the customer in the db
        } elseif ($customer = Customer::where('name', $customerName)->oneOrNull()) {
            $this->customersByName[$customerName] = $customer;
            $this->customersByNumber[$customer->number] = $customer;

            return $customer;
        }

        // iii. create the customer
        $customer = new Customer();
        if ($customer->create($profile)) {
            $this->customersByName[$customerName] = $customer;
            $this->customersByNumber[$customer->number] = $customer;

            return $customer;
        }

        return null;
    }
}
