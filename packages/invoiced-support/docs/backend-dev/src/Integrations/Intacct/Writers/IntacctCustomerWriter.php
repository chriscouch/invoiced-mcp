<?php

namespace App\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\Countries;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractCustomerWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Traits\WriterTrait;
use Intacct\Functions\AccountsReceivable\AbstractCustomer;
use Intacct\Functions\AccountsReceivable\CustomerCreate;
use Intacct\Functions\AccountsReceivable\CustomerUpdate;
use App\Core\Orm\Model;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class IntacctCustomerWriter extends AbstractCustomerWriter implements LoggerAwareInterface
{
    use WriterTrait, LoggerAwareTrait;

    public function __construct(private IntacctApi $intacctApi)
    {
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    public function performCreate(Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->intacctApi->setAccount($account);

        try {
            $this->createIntacctCustomer($customer, $syncProfile, $account);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performUpdate(AccountingCustomerMapping $mapping, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->intacctApi->setAccount($account);

        try {
            // build the request
            $customerRequest = $this->buildIntacctRequest($customer, null !== $mapping, $syncProfile);

            if ($account->sync_all_entities) {
                $entity = $this->getIntacctEntity($customer);
                if (empty($entity)) {
                    $this->intacctApi->createTopLevelObject($customerRequest);
                } else {
                    $this->intacctApi->createObjectInEntity($customerRequest, $entity);
                }
            } elseif ($syncProfile->customer_top_level) {
                $this->intacctApi->createTopLevelObject($customerRequest);
            } else {
                $this->intacctApi->createObject($customerRequest);
            }
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * @throws IntegrationApiException
     */
    public function createIntacctCustomer(Customer $customer, IntacctSyncProfile $syncProfile, IntacctAccount $intacctAccount): string
    {
        $useTopLevel = $syncProfile->customer_top_level;
        $syncAllEntities = $intacctAccount->sync_all_entities;
        $entity = $this->getIntacctEntity($customer);

        // look up customer
        $intacctCustomer = $this->intacctApi->getCustomerByNumber($customer->number, $useTopLevel, ['RECORDNO'], $entity, $syncAllEntities);
        $mappingSource = AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM; // set created by Intacct by default. Overwritten in if condition.

        if (!$intacctCustomer) {
            // build the request
            $customerRequest = $this->buildIntacctRequest($customer, false, $syncProfile);

            // create the customer
            if ($syncAllEntities) {
                if ($entity) {
                    $intacctId = $this->intacctApi->createObjectInEntity($customerRequest, $entity);
                } else {
                    $intacctId = $this->intacctApi->createTopLevelObject($customerRequest);
                }
            } elseif ($useTopLevel) {
                $intacctId = $this->intacctApi->createTopLevelObject($customerRequest);
            } else {
                $intacctId = $this->intacctApi->createObject($customerRequest);
            }

            // map as created by Invoiced
            $mappingSource = AccountingCustomerMapping::SOURCE_INVOICED;
        } else {
            $intacctId = (string) $intacctCustomer->{'RECORDNO'};
        }

        $this->saveCustomerMapping($customer, $syncProfile->getIntegrationType(), $intacctId, $mappingSource);

        return $intacctId;
    }

    public function buildIntacctRequest(Customer $customer, bool $isUpdate, IntacctSyncProfile $syncProfile): AbstractCustomer
    {
        $intacctCustomer = $isUpdate ? new CustomerUpdate() : new CustomerCreate();

        $intacctCustomer->setCustomerName($customer->name);
        $intacctCustomer->setPrintAs($customer->name);
        // NOTE: Intacct does not allow the customer ID to be modified. On update
        // this is used to reference the existing account.
        $intacctCustomer->setCustomerId($customer->number);
        if ($address1 = $customer->address1) {
            $intacctCustomer->setAddressLine1($address1);
        }
        if ($address2 = $customer->address2) {
            $intacctCustomer->setAddressLine2($address2);
        }
        if ($city = $customer->city) {
            $intacctCustomer->setCity($city);
        }
        if ($state = $customer->state) {
            $intacctCustomer->setStateProvince($state);
        }
        if ($zip = $customer->postal_code) {
            $intacctCustomer->setZipPostalCode($zip);
        }
        if ($phone = $customer->phone) {
            $intacctCustomer->setPrimaryPhoneNo($phone);
        }
        // NOTE: Intacct value, COUNTRY, requires a full country name
        // to update the country on the customer object. When creating,
        // the country name is interpreted by the COUNTRYCODE value.
        // It's important to specify the COUNTRY value so that it is
        // properly updated.
        $countryCode = $customer->country ?: (string) $customer->tenant()->country;
        $intacctCustomer->setIsoCountryCode($countryCode);
        $country = (new Countries())->get($countryCode) ?? [];
        $countryName = $country['country'] ?? null;
        if ('MK' == $countryCode) {
            // INVD-3819: Intacct does not recognize "North Macedonia" so we must change it to "Macedonia".
            // If Intacct ever updates their country list then this workaround can be removed.
            $intacctCustomer->setCountry('Macedonia');
        } elseif ($countryName) {
            $intacctCustomer->setCountry($countryName);
        }

        if ($email = $customer->email) {
            $intacctCustomer->setPrimaryEmailAddress($email);
        }

        // custom fields
        if ($customFieldMapping = $syncProfile->customer_custom_field_mapping) {
            $intacctCustomer->setCustomFields($this->buildCustomFields($customFieldMapping, $customer));
        }

        return $intacctCustomer;
    }

    public function getIntacctEntity(Customer $customer) : ?string
    {
        $metadata = $customer->metadata;
        if ($metadata !== null && property_exists($metadata, 'intacct_entity')) {
            return $metadata->intacct_entity;
        }

        return null;
    }
}
