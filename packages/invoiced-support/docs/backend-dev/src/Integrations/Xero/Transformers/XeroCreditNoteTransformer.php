<?php

namespace App\Integrations\Xero\Transformers;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractCreditNoteTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Xero\Libs\XeroMapper;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\PaymentProcessing\Models\PaymentMethod;

/**
 * @property XeroSyncProfile $syncProfile
 */
class XeroCreditNoteTransformer extends AbstractCreditNoteTransformer
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
        // make sure it's a credit note that has been approved
        if ('ACCRECCREDIT' != $input->document->Type) {
            return null;
        }

        $status = $input->document->Status;
        if (!in_array($status, ['AUTHORISED', 'PAID', 'VOIDED'])) {
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

        // Allocations
        $record['payments'] = $this->buildAllocations($record['currency'], $record['customer'], $input);

        return $record;
    }

    private function buildAllocations(string $currency, array $customer, AccountingJsonRecord $input): array
    {
        // parse credit note applications
        $applications = [];
        foreach ($input->document->Allocations ?? [] as $xeroAllocation) {
            $amount = Money::fromDecimal($currency, (float) $xeroAllocation->Amount);
            $date = $this->mapper->parseUnixDate($xeroAllocation->Date);
            $applications[] = [
                // Allocations do not have an ID so we have to make one that is unique
                'accounting_id' => md5($input->document->CreditNoteID.'/'.$xeroAllocation->Invoice->InvoiceID.'/'.$xeroAllocation->Date),
                'date' => $date->getTimestamp(),
                'method' => PaymentMethod::OTHER,
                'currency' => $currency,
                'customer' => $customer,
                'applied_to' => [
                    [
                        'amount' => $amount->toDecimal(),
                        'type' => 'credit_note',
                        'invoice' => [
                            'accounting_id' => $xeroAllocation->Invoice->InvoiceID,
                            'number' => $xeroAllocation->Invoice->InvoiceNumber,
                        ],
                        'credit_note' => [
                            'accounting_id' => $input->document->CreditNoteID,
                            'number' => $input->document->CreditNoteNumber,
                        ],
                        'document_type' => 'invoice',
                    ],
                ],
            ];
        }

        return $applications;
    }
}
