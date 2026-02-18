<?php

namespace App\Integrations\Xero\Transformers;

use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractPaymentTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Xero\Libs\XeroMapper;
use App\PaymentProcessing\Models\PaymentMethod;

class XeroBatchPaymentTransformer extends AbstractPaymentTransformer
{
    public function getMappingObjectType(): string
    {
        return 'batch_payment';
    }

    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // only sync batch payments which are a cash receipt
        if ('RECBATCH' != $input->document->Type) {
            return null;
        }

        // handle voided payments
        if ('DELETED' == $input->document->Status) {
            return [
                'accounting_id' => $input->document->BatchPaymentID,
                'voided' => true,
            ];
        }

        $record['method'] = PaymentMethod::OTHER;

        // Date
        $mapper = new XeroMapper();
        $paymentDate = $mapper->parseUnixDate($input->document->Date ?? '');
        $record['date'] = $paymentDate->getTimestamp();

        // Process payment line items within batch payment.
        // NOTE: The customer is not available in the batch payment record.
        // The customer will instead be inherited from the documents the
        // payment is applied to.
        $record['applied_to'] = [];
        $currency = null;
        $company = $this->syncProfile->tenant();
        foreach ($input->document->Payments as $xeroPayment) {
            // The currency is not specified in the batch payment so we must attempt
            // to locate it based on the first invoice that is referenced. If the
            // first invoice is not found then we will fall back to the company currency.
            // This will not always be accurate in some multi-currency scenarios, such as,
            // if the first invoice does not exist on Invoiced and the currency is a non-default currency.
            $invoiceNumber = $xeroPayment->Invoice->InvoiceNumber ?? null;
            if (!$currency && $invoiceNumber) {
                $invoice = Invoice::where('number', $invoiceNumber)->oneOrNull();
                $currency = $invoice?->currency;
            }

            $jsonPayment = new AccountingJsonRecord($xeroPayment);
            $record['applied_to'][] = $mapper->buildPaymentSplit($jsonPayment);
        }

        $record['currency'] = $currency ?? $company->currency;

        return $record;
    }
}
