<?php

namespace App\Integrations\Intacct\Transformers;

use App\Imports\Libs\ImportHelper;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use SimpleXMLElement;

class IntacctOrderEntryInvoiceTransformer extends AbstractIntacctOrderEntryTransformer
{
    /**
     * @param AccountingXmlRecord $intacctInvoice
     */
    public function transform(AccountingRecordInterface $intacctInvoice): ?AccountingInvoice
    {
        $customer = $this->buildCustomer($intacctInvoice->document);
        if (!$customer) {
            return null;
        }

        $documentValues = $this->buildDocumentValues($intacctInvoice->document);
        if (!$documentValues) {
            return null;
        }

        $installments = $documentValues['installments'];
        unset($documentValues['installments']);

        // Customization to use ship to email
        // addresses as an email distribution list
        $contactList = [];
        $distributionSettings = [];
        if (isset($intacctInvoice->document->{'SHIPTO'}->{'EMAIL1'})) {
            $shipToEmails = [];
            if ($email = (string) $intacctInvoice->document->{'SHIPTO'}->{'EMAIL1'}) {
                $shipToEmails = array_merge($shipToEmails, ImportHelper::parseEmailAddress($email));
            }

            if ($email = (string) $intacctInvoice->document->{'SHIPTO'}->{'EMAIL2'}) {
                $shipToEmails = array_merge($shipToEmails, ImportHelper::parseEmailAddress($email));
            }

            if (count($shipToEmails) > 0) {
                $contactList = [
                    'department' => (string) $intacctInvoice->document->{'SHIPTO'}->{'PRINTAS'},
                    'emails' => $shipToEmails,
                ];

                $distributionSettings = [
                    'department' => (string) $intacctInvoice->document->{'SHIPTO'}->{'PRINTAS'},
                    'enabled' => true,
                ];
            }
        }

        $delivery = $documentValues['delivery'] ?? [];
        unset($documentValues['delivery']);

        if ($entity_id = (string) $intacctInvoice->document->{'MEGAENTITYID'}) {
            $documentValues['metadata']['intacct_entity'] = $entity_id;
        }

        return new AccountingInvoice(
            integration: IntegrationType::Intacct,
            accountingId: (string) $intacctInvoice->document->{'PRRECORDKEY'},
            customer: $customer,
            values: $documentValues,
            pdf: $intacctInvoice->pdf,
            installments: $installments,
            contactList: $contactList,
            distributionSettings: $distributionSettings,
            delivery: $delivery,
        );
    }

    protected function buildDocumentValues(SimpleXMLElement $intacctInvoice): ?array
    {
        $values = parent::buildDocumentValues($intacctInvoice);
        if (!$values) {
            return null;
        }

        // payment terms
        if ($terms = (string) $intacctInvoice->{'TERM'}->{'NAME'}) {
            $values['payment_terms'] = $terms;
        }

        // due date
        if ($dueDate = (string) $intacctInvoice->{'WHENDUE'}) {
            $values['due_date'] = $this->mapper->parseIsoDate($dueDate, true);
        }

        return $values;
    }
}
