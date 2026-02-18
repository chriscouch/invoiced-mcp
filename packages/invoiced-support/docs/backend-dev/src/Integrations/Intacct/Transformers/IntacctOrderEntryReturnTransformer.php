<?php

namespace App\Integrations\Intacct\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;

class IntacctOrderEntryReturnTransformer extends AbstractIntacctOrderEntryTransformer
{
    /**
     * @param AccountingXmlRecord $intacctReturn
     */
    public function transform(AccountingRecordInterface $intacctReturn): ?AccountingCreditNote
    {
        $customer = $this->buildCustomer($intacctReturn->document);
        if (!$customer) {
            return null;
        }

        $documentValues = $this->buildDocumentValues($intacctReturn->document);
        if (!$documentValues) {
            return null;
        }

        if ($entity_id = (string) $intacctReturn->document->{'MEGAENTITYID'}) {
            $documentValues['metadata']['intacct_entity'] = $entity_id;
        }

        return new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: (string) $intacctReturn->document->{'PRRECORDKEY'},
            customer: $customer,
            values: $documentValues,
            pdf: $intacctReturn->pdf,
        );
    }
}
