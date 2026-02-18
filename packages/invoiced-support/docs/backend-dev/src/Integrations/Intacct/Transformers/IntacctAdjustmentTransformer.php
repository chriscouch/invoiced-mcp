<?php

namespace App\Integrations\Intacct\Transformers;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Imports\Libs\ImportHelper;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Integrations\Intacct\Models\IntacctAccount;
use SimpleXMLElement;

class IntacctAdjustmentTransformer implements TransformerInterface
{
    private const ADJUSTMENT_STATE_REVERSED = 'Reversed';
    private const ADJUSTMENT_STATE_REVERSAL = 'Reversal';

    private IntacctMapper $mapper;
    private bool $hasMultiCurrency = false;
    private bool $billToCustomerSource = false;

    public function __construct()
    {
        $this->mapper = new IntacctMapper();
    }

    /**
     * @param IntacctAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->hasMultiCurrency = $account->tenant()->features->has('multi_currency');
    }

    /**
     * @param AccountingXmlRecord $adjustment
     */
    public function transform(AccountingRecordInterface $adjustment): ?AccountingCreditNote
    {
        $adjustmentData = $adjustment->document;
        $state = $adjustmentData->{'STATE'};
        if (self::ADJUSTMENT_STATE_REVERSAL == $state) {
            return null;
        }

        $record = [
            'items' => [],
        ];
        $currency = strtolower((string) $adjustmentData->{'CURRENCY'});

        /* Build line item data. */
        foreach ((array) $adjustment->lines as $itemXml) {
            $unitCost = $this->hasMultiCurrency
                ? -1 * (float) $itemXml->{'TRX_AMOUNT'}
                : -1 * (float) $itemXml->{'AMOUNT'};

            $record['items'][] = [
                'name' => (string) $itemXml->{'ENTRYDESCRIPTION'},
                'quantity' => 1,
                'unit_cost' => Money::fromDecimal($currency, $unitCost)->toDecimal(),
            ];
        }

        $record = array_merge($record, [
            'currency' => $currency,
            'date' => $this->mapper->parseDate((string) $adjustmentData->{'WHENPOSTED'}),
            'notes' => (string) $adjustmentData->{'DESCRIPTION'},
            'number' => (string) $adjustmentData->{'RECORDID'},
        ]);

        if ($entity_id = (string) $adjustmentData->{'MEGAENTITYID'}) {
            $record['metadata']['intacct_entity'] = $entity_id;
        }

        return new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: (string) $adjustmentData->{'RECORDNO'},
            customer: $this->buildCustomer($adjustment),
            values: $record,
            voided: self::ADJUSTMENT_STATE_REVERSED == $state,
        );
    }

    protected function buildCustomer(AccountingXmlRecord $adjustment): ?AccountingCustomer
    {
        // are we importing from a bill to contact or an A/R customer?
        if ($this->billToCustomerSource) {
            $customer = $this->buildCustomerFromIntacctBillTo($adjustment->document);
        } else {
            $customer = $this->buildCustomerFromIntacctCustomer($adjustment->document);
        }

        return $customer;
    }

    /**
     * This determines the customer parameters
     * from an Intacct bill to contact.
     *
     * @throws TransformException when the customer cannot be determined
     */
    public function buildCustomerFromIntacctBillTo(SimpleXMLElement $adjustment): AccountingCustomer
    {
        $customer = [
            'name' => (string) $adjustment->{'BILLTO.PRINTAS'},
            'address1' => (string) $adjustment->{'BILLTO.MAILADDRESS.ADDRESS1'},
            'address2' => (string) $adjustment->{'BILLTO.MAILADDRESS.ADDRESS2'},
            'city' => (string) $adjustment->{'BILLTO.MAILADDRESS.CITY'},
            'state' => (string) $adjustment->{'BILLTO.MAILADDRESS.STATE'},
            'postal_code' => (string) $adjustment->{'BILLTO.MAILADDRESS.ZIP'},
            'phone' => (string) $adjustment->{'BILLTO.PHONE1'},
            'metadata' => [
                'intacct_customer_number' => trim($adjustment->{'CUSTOMERID'}),
            ],
        ];

        if ($country = (string) $adjustment->{'BILLTO.MAILADDRESS.COUNTRYCODE'}) {
            $customer['country'] = $country;
        }

        // email address(es)
        $emails = [];
        if ($email = (string) $adjustment->{'BILLTO.EMAIL1'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        }

        if ($email = (string) $adjustment->{'BILLTO.EMAIL2'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        }

        return new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: (string) $adjustment->{'BILLTOPAYTOKEY'},
            values: $customer,
            emails: $emails,
        );
    }

    /**
     * This determines the customer parameters from an Intacct customer.
     *
     * @throws TransformException
     */
    public function buildCustomerFromIntacctCustomer(SimpleXMLElement $adjustment): ?AccountingCustomer
    {
        return new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '', // The adjustment data does not include the customer record number
            values: [
                'name' => (string) $adjustment->{'CUSTOMERNAME'},
                'number' => (string) $adjustment->{'CUSTOMERID'},
            ],
        );
    }
}
