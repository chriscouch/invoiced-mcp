<?php

namespace App\Integrations\QuickBooksOnline\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractCustomerWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Core\Orm\Model;
use stdClass;

class QuickBooksCustomerWriter extends AbstractCustomerWriter
{
    public function __construct(private QuickBooksApi $quickbooksApi)
    {
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performCreate(Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            $this->createQBOCustomer($customer, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performUpdate(AccountingCustomerMapping $mapping, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            $customerId = $mapping->accounting_id;

            // build update request params.
            $customerDetails = $this->buildQBOCustomerDetails($customer, $syncProfile);
            $customerDetails['sparse'] = true;

            // obtain sync token from QBO.
            $qboCustomer = $this->quickbooksApi->getCustomer($customerId);
            $customerSyncToken = (string) $qboCustomer->SyncToken;

            // update customer in QBO.
            $this->quickbooksApi->updateCustomer($customerId, $customerSyncToken, $customerDetails);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Creates customer on QBO if no customer exists
     * with the Invoiced's customer's name. Creates
     * AccountingCustomerMapping with QBO customer's
     * details.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function createQBOCustomer(Customer $customer, QuickBooksOnlineSyncProfile $syncProfile): stdClass
    {
        if (!$syncProfile->write_customers) {
            throw new SyncException('Unable to write customer to QuickBooks Online. Writing customers is disabled.');
        }

        // check if customer is present in QBO.
        $qboCustomerName = $this->formatCustomerName($customer, $syncProfile);
        $qboCustomer = $this->quickbooksApi->getCustomerByName($qboCustomerName);
        $mappingSource = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM; // set created by QBO by default. Overwritten in if condition.

        if (!$qboCustomer) {
            // build QBO request params.
            $customerDetails = $this->buildQBOCustomerDetails($customer, $syncProfile);

            // create customer on QBO.
            $qboCustomer = $this->quickbooksApi->createCustomer($customerDetails);

            // created by Invoiced
            $mappingSource = AccountingCustomerMapping::SOURCE_INVOICED;
        }

        $this->saveCustomerMapping($customer, $syncProfile->getIntegrationType(), $qboCustomer->Id, $mappingSource);

        return $qboCustomer;
    }

    /**
     * Builds a QBO customer configuration using an Invoiced
     * customer object.
     */
    private function buildQBOCustomerDetails(Customer $customer, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        // Format display name.
        $displayName = $this->formatCustomerName($customer, $syncProfile);

        $details = [
            'BillAddr' => [
                'Line1' => $customer->address1,
                'Line2' => $customer->address2,
                'City' => $customer->city,
                'Country' => $customer->country,
                'CountrySubDivisionCode' => $customer->state,
                'PostalCode' => $customer->postal_code,
            ],
            'Notes' => substr((string) $customer->notes, 0, 4000),
            'DisplayName' => $displayName,
            'PrimaryPhone' => [
                'FreeFormNumber' => substr((string) $customer->phone, 0, 21),
            ],
            'PrimaryEmailAddr' => [
                'Address' => substr((string) $customer->email, 0, 100),
            ],
        ];

        if ($customer->tenant()->features->has('multi_currency')) {
            $details['CurrencyRef'] = [
                'value' => $customer->calculatePrimaryCurrency(),
            ];
        }

        // set default values in order to clear the relationship in case it was deleted in Invoiced.
        $details['ParentRef']['value'] = null;
        $details['Job'] = false;
        $details['BillWithParent'] = false;

        $parentCustomer = $customer->parentCustomer();
        if ($customer->parent_customer && $parentCustomer) {
            $parentAccountingCustomerMapping
                = AccountingCustomerMapping::findForCustomer($parentCustomer, $syncProfile->getIntegrationType());

            if (!$parentAccountingCustomerMapping) {
                // we can't add a parent customer if we don't have the id from the accounting system
                return $details;
            }
            $details['ParentRef'] = [
                'value' => $parentAccountingCustomerMapping->accounting_id,
                'name' => $this->formatCustomerName($parentCustomer, $syncProfile)
            ];
            $details['Job'] = true;
            $details['BillWithParent'] = $customer->bill_to_parent ?: false;
        }

        return $details;
    }

    /**
     * Formats customer name for QBO.
     */
    private function formatCustomerName(Customer $customer, QuickBooksOnlineSyncProfile $syncProfile): string
    {
        $name = $customer->name;
        if (!$name) {
            $name = $customer->number;
        } elseif ($syncProfile->namespace_customers) {
            $name .= ' '.$customer->number;
        }

        if ('US' == $customer->tenant()->country) {
            // strip unsupported characters
            $converted = iconv('UTF-8', 'ISO-8859-1//IGNORE', $name);
            if ($converted) {
                $name = (string) iconv('ISO-8859-1', 'UTF-8', $converted);
            }
        }

        // Display name cannot contain colon, tab, or newline characters
        $name = str_replace([':', "\t", "\n"], ['', '', ''], $name);

        return substr($name, 0, 500);
    }
}
