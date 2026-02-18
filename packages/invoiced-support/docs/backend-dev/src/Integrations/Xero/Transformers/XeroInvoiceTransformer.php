<?php

namespace App\Integrations\Xero\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractInvoiceTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Xero\Libs\XeroMapper;
use App\Integrations\Xero\Models\XeroSyncProfile;

/**
 * @property XeroSyncProfile $syncProfile
 */
class XeroInvoiceTransformer extends AbstractInvoiceTransformer
{
    private const LINE_AMOUNT_TYPE_EXCLUSIVE = 'Exclusive';

    private XeroMapper $mapper;

    public function __construct()
    {
        $this->mapper = new XeroMapper();
    }

    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // make sure it's an A/R invoice that has been approved
        if ('ACCREC' != $input->document->Type) {
            return null;
        }

        if (!in_array($input->document->Status, ['AUTHORISED', 'PAID', 'VOIDED'])) {
            return null;
        }

        // Repeating invoices in Xero will create a future-dated invoice
        // that does not have an invoice # yet assigned. When there is no
        // invoice number assigned we should skip importing that record until
        // there is an invoice number.
        if (!$record['number']) {
            return null;
        }

        // enable draft mode for new documents
        if ($this->syncProfile->read_invoices_as_drafts) {
            $record['draft'] = true;
        }

        // Purchase Order
        if (isset($record['purchase_order'])) {
            $record['purchase_order'] = substr($record['purchase_order'], 0, 32);
            $record['metadata']['xero_reference'] = substr($record['metadata']['xero_reference'], 0, 255);
        }

        // Line Items, Discount, and Tax
        [$record['discount'], $record['items']] = $this->mapper->buildLineItems($record['currency'], $input->document->LineItems);
        $record['tax'] = self::LINE_AMOUNT_TYPE_EXCLUSIVE == $input->document->LineAmountTypes ? (float) $input->document->TotalTax : 0;

        $record['voided'] = 'VOIDED' == $input->document->Status;

        return $record;
    }
}
