<?php

namespace App\Integrations\Intacct\Transformers;

use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Core\Orm\Model;
use SimpleXMLElement;

class IntacctArInvoiceTransformer implements TransformerInterface
{
    private const ITEM_FIELDS = [
        'ITEMNAME',
        'ENTRYDESCRIPTION',
        'AMOUNT',
    ];

    private IntacctMapper $mapper;
    private bool $importDrafts = false;

    public function __construct(
        private IntacctApi $client,
    ) {
        $this->mapper = new IntacctMapper();
    }

    /**
     * Sets the API client (for testing).
     */
    public function setClient(IntacctApi $client): void
    {
        $this->client = $client;
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->client->setAccount($account);
        $this->importDrafts = $syncProfile->read_invoices_as_drafts;
    }

    /**
     * @param AccountingXmlRecord $intacctInvoice
     */
    public function transform(AccountingRecordInterface $intacctInvoice): ?AccountingInvoice
    {
        $intacctInvoiceId = (string) $intacctInvoice->document->{'RECORDNO'};
        $record = [
            // The accounting system should have already calculated any tax
            'calculate_taxes' => false,
            'metadata' => [],
        ];

        // invoice #
        if ($number = (string) $intacctInvoice->document->{'RECORDID'}) {
            $record['number'] = trim($number);

            // look for existing invoices by number
            // and skip if one already exists
            if ($record['number']) {
                $numInvoices = Invoice::where('number', $record['number'])->count();
                if ($numInvoices > 0) {
                    return null;
                }
            }
        }

        // currency
        if ($currency = (string) $intacctInvoice->document->{'CURRENCY'}) {
            $record['currency'] = strtolower($currency);
        }

        // enable draft mode for new documents
        if ($this->importDrafts) {
            $record['draft'] = true;
        }

        // date
        if ($date = (string) $intacctInvoice->document->{'WHENPOSTED'}) {
            $record['date'] = $this->mapper->parseDate($date);
        }

        // due date
        if ($dueDate = (string) $intacctInvoice->document->{'WHENDUE'}) {
            $record['due_date'] = $this->mapper->parseDate($dueDate, true);
        }

        // po number
        if ($poNumber = (string) $intacctInvoice->document->{'DOCNUMBER'}) {
            $record['metadata']['intacct_purchase_order'] = $poNumber;
            $record['purchase_order'] = substr($poNumber, 0, 32);
        }

        // look up the line items from Intacct
        try {
            $intacctLines = $this->client->getArInvoiceLines($intacctInvoiceId, self::ITEM_FIELDS);
        } catch (IntegrationApiException $e) {
            throw new TransformException('Could not retrieve invoice lines: '.$e->getMessage(), 0, $e);
        }

        // map the line items into our invoice
        $record['items'] = [];
        foreach ($intacctLines->getData() as $intacctLine) {
            if (!empty($intacctLine->{'ITEMNAME'})) { // inventory item
                $name = trim((string) $intacctLine->{'ITEMNAME'});
            } elseif (!empty($intacctLine->{'ENTRYDESCRIPTION'})) { // one-off line
                // clean up the line item name
                // remove any trailing ':'
                $name = trim((string) $intacctLine->{'ENTRYDESCRIPTION'});
                if (str_ends_with($name, ':')) {
                    $name = substr($name, 0, -1);
                }
            } else {
                $name = '';
            }

            $item = [
                'name' => $name,
                'unit_cost' => (float) $intacctLine->{'AMOUNT'},
            ];

            $record['items'][] = $item;
        }

        // DEBUG: look for invoices with no line items
        if (0 === count($record['items'])) {
            throw new TransformException('Invoice is missing line items!');
        }

        // ship to address
        if ($address1 = (string) $intacctInvoice->document->{'SHIPTO.MAILADDRESS.ADDRESS1'}) {
            $shipTo['name'] = (string) $intacctInvoice->document->{'SHIPTO.PRINTAS'};
            $shipTo['address1'] = $address1;
            $shipTo['address2'] = (string) $intacctInvoice->document->{'SHIPTO.MAILADDRESS.ADDRESS2'};
            $shipTo['city'] = (string) $intacctInvoice->document->{'SHIPTO.MAILADDRESS.CITY'};
            $shipTo['state'] = (string) $intacctInvoice->document->{'SHIPTO.MAILADDRESS.STATE'};
            $shipTo['postal_code'] = (string) $intacctInvoice->document->{'SHIPTO.MAILADDRESS.ZIP'};
            if ($country = (string) $intacctInvoice->document->{'SHIPTO.MAILADDRESS.COUNTRYCODE'}) {
                $shipTo['country'] = $country;
            }
            $record['ship_to'] = $shipTo;
        }

        if ($entity_id = (string) $intacctInvoice->document->{'MEGAENTITYID'}) {
            $record['metadata']['intacct_entity'] = $entity_id;
        }

        return new AccountingInvoice(
            integration: IntegrationType::Intacct,
            accountingId: $intacctInvoiceId,
            customer: $this->loadIntacctCustomer($intacctInvoice->document),
            values: $record,
            voided: false,
        );
    }

    /**
     * Determines the customer for an imported invoice.
     *
     * @throws TransformException when an API error occurrs
     */
    private function loadIntacctCustomer(SimpleXMLElement $intacctInvoice): AccountingCustomer
    {
        return new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: (string) $intacctInvoice->document->{'CUSTREC'},
            values: [
                'name' => (string) $intacctInvoice->document->{'CUSTOMERNAME'},
                'number' => (string) $intacctInvoice->document->{'CUSTOMERID'},
            ],
        );
    }
}
