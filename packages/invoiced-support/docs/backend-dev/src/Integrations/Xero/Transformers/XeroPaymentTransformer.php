<?php

namespace App\Integrations\Xero\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractPaymentTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Xero\Libs\XeroMapper;
use App\PaymentProcessing\Models\PaymentMethod;

class XeroPaymentTransformer extends AbstractPaymentTransformer
{
    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // only sync payments which are a cash receipt
        if ('ACCRECPAYMENT' != $input->document->PaymentType) {
            return null;
        }

        // ignore batch payments because they are synced separately
        if (property_exists($input->document, 'BatchPaymentID')) {
            return null;
        }

        // handle voided payments
        if ('DELETED' == $input->document->Status) {
            return [
                'accounting_id' => $input->document->PaymentID,
                'voided' => true,
            ];
        }

        $record['method'] = PaymentMethod::OTHER;

        // Date
        $mapper = new XeroMapper();
        $paymentDate = $mapper->parseUnixDate($input->document->Date ?? '');
        $record['date'] = $paymentDate->getTimestamp();

        // Applied To
        $record['applied_to'] = [
            $mapper->buildPaymentSplit($input),
        ];

        return $record;
    }
}
