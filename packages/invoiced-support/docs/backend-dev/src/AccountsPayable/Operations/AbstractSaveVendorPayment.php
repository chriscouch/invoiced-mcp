<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Enums\VendorPaymentItemTypes;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentItem;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;

abstract class AbstractSaveVendorPayment
{
    public function __construct(
        private readonly BillStatusTransition $billStatusTransition,
    ) {
    }

    protected function saveRelatedDocumentStatuses(VendorPayment $payment): void
    {
        foreach ($payment->getItems() as $item) {
            if ($bill = $item->bill) {
                $this->billStatusTransition->transitionStatus($bill);
            }
        }
    }

    protected function saveItems(VendorPayment $payment, array $appliedTo): array
    {
        $items = [];
        foreach ($appliedTo as $row) {
            // Lookup existing payment items
            // or create if no id
            if (isset($row['id'])) {
                $paymentItem = VendorPaymentItem::where('vendor_payment_id', $payment)
                    ->where('id', $row['id'])
                    ->one();
            } else {
                $paymentItem = new VendorPaymentItem();
                $paymentItem->vendor_payment = $payment;
            }

            if (isset($row['type']) && 'convenience_fee' === $row['type']) {
                $paymentItem->type = VendorPaymentItemTypes::ConvenienceFee;
            }

            if (isset($row['bill']) && !$row['bill'] instanceof Bill) {
                $paymentItem->bill = Bill::findOrFail($row['bill']);
                $paymentItem->type = VendorPaymentItemTypes::Application;

                unset($row['bill']);
            }

            if (isset($row['vendor_credit']) && !$row['vendor_credit'] instanceof VendorCredit) {
                $paymentItem->vendor_credit = VendorCredit::findOrFail($row['vendor_credit']);
                $paymentItem->type = VendorPaymentItemTypes::Application;

                unset($row['vendor_credit']);
            }
            unset($row['type']);

            foreach ($row as $k => $v) {
                $paymentItem->$k = $v;
            }
            $paymentItem->saveOrFail();
            $items[] = $paymentItem;
        }
        $payment->setItems($items);

        return $items;
    }

    public function validateAppliedAmount(VendorPayment $payment): void
    {
        // validate the payment items do not exceed payment amount
        $amount = $payment->amount;
        $amount = Money::fromDecimal($payment->currency, $amount);

        // add up items
        $itemAmount = Money::zero($payment->currency);
        foreach ($payment->getItems() as $paymentItem) {
            $itemAmount = $itemAmount->add(Money::fromDecimal($payment->currency, $paymentItem->amount));
        }

        if ($itemAmount->greaterThan($amount)) {
            throw new ModelException('Amount applied ('.$itemAmount.') exceeds the payment amount ('.$amount.')');
        }
    }
}
