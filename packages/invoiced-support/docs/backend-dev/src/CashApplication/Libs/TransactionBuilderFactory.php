<?php

namespace App\CashApplication\Libs;

use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Interfaces\TransactionBuilderInterface;
use App\CashApplication\TransactionBuilder\AppliedCreditSplit;
use App\CashApplication\TransactionBuilder\DocumentAdjustmentSplit;
use App\CashApplication\TransactionBuilder\ConvenienceFeeSplit;
use App\CashApplication\TransactionBuilder\CreditNoteSplit;
use App\CashApplication\TransactionBuilder\CreditSplit;
use App\CashApplication\TransactionBuilder\EstimateSplit;
use App\CashApplication\TransactionBuilder\InvoiceSplit;

class TransactionBuilderFactory
{
    /**
     * @throws ApplyPaymentException
     */
    public function make(array $split): TransactionBuilderInterface
    {
        if (!isset($split['type'])) {
            throw new ApplyPaymentException('Must provide a type for each transaction.');
        }

        $type = PaymentItemType::tryFrom($split['type']);
        if (!$type) {
            throw new ApplyPaymentException('Unrecognized split type: '.$split['type']);
        }

        return match ($type) {
            PaymentItemType::AppliedCredit => new AppliedCreditSplit(),
            PaymentItemType::ConvenienceFee => new ConvenienceFeeSplit(),
            PaymentItemType::Credit => new CreditSplit(),
            PaymentItemType::CreditNote => new CreditNoteSplit(),
            PaymentItemType::DocumentAdjustment => new DocumentAdjustmentSplit(),
            PaymentItemType::Estimate => new EstimateSplit(),
            PaymentItemType::Invoice => new InvoiceSplit(),
        };
    }
}
