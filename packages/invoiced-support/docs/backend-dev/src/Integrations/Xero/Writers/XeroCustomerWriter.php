<?php

namespace App\Integrations\Xero\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractCustomerWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Core\Orm\Model;
use stdClass;

class XeroCustomerWriter extends AbstractCustomerWriter
{
    public function __construct(private XeroApi $xeroApi)
    {
    }

    /**
     * @param XeroAccount $account
     */
    protected function performCreate(Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->xeroApi->setAccount($account);

        try {
            $this->createXeroCustomer($customer, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount $account
     */
    protected function performUpdate(AccountingCustomerMapping $mapping, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->xeroApi->setAccount($account);

        try {
            $request = $this->buildRequest($customer);
            $request['ContactID'] = $mapping->accounting_id;
            $this->xeroApi->createOrUpdate('Contacts', $request);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    private function buildRequest(Customer $customer): array
    {
        $number = $this->stripQuotes($customer->number);
        $request = [
            'Name' => $this->stripQuotes($customer->name),
            'ContactNumber' => $number,
            'AccountNumber' => $number,
            'ContactStatus' => $customer->active ? 'ACTIVE' : 'ARCHIVED',
            'EmailAddress' => $customer->email,
            'Addresses' => [
                [
                    'AddressLine1' => $customer->address1,
                    'AddressLine2' => $customer->address2,
                    'City' => $customer->city,
                    'Country' => $customer->country,
                    'PostalCode' => $customer->postal_code,
                    'Region' => $customer->state,
                    'AddressType' => 'STREET',
                ],
            ],
        ];

        if ($customer->phone) {
            $request['Phones'] = [
                [
                    'PhoneNumber' => $customer->phone,
                    'PhoneType' => 'DEFAULT',
                ],
            ];
        }

        if ($currency = $customer->currency) {
            $request['DefaultCurrency'] = strtoupper($currency);
        }

        if ($taxId = $customer->tax_id) {
            $request['TaxNumber'] = $taxId;
        }

        return $request;
    }

    /**
     * @throws SyncException|IntegrationApiException
     */
    public function createXeroCustomer(Customer $customer, AccountingSyncProfile $syncProfile): stdClass
    {
        // First check for an existing customer on Xero
        $xeroCustomer = $this->getExistingCustomer($customer);
        $mappingSource = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM;

        // If none found then create a new customer
        if (!$xeroCustomer) {
            if (!$syncProfile->write_customers) {
                throw new SyncException('Unable to write customer to Xero. Writing customers is disabled.');
            }

            $request = $this->buildRequest($customer);
            $xeroCustomer = $this->xeroApi->createOrUpdate('Contacts', $request);
            $mappingSource = AccountingCustomerMapping::SOURCE_INVOICED;
        }

        $this->saveCustomerMapping($customer, $syncProfile->getIntegrationType(), $xeroCustomer->ContactID, $mappingSource);

        return $xeroCustomer;
    }

    private function getExistingCustomer(Customer $customer): ?stdClass
    {
        // check if customer is present in Xero first by contact name
        $name = $this->stripQuotes($customer->name);
        $xeroCustomers = $this->xeroApi->getMany('Contacts', [
            'where' => 'Name=="'.$name.'"',
            'includeArchived' => 'true',
        ]);
        if (1 == count($xeroCustomers)) {
            return $xeroCustomers[0];
        }

        // then check based on account #
        $number = $this->stripQuotes($customer->number);
        $xeroCustomers = $this->xeroApi->getMany('Contacts', [
            'where' => 'AccountNumber=="'.$number.'"',
            'includeArchived' => 'true',
        ]);

        return 1 == count($xeroCustomers) ? $xeroCustomers[0] : null;
    }
}
