<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\PaymentProcessing\Models\PaymentMethod;
use App\CashApplication\Models\Transaction;

class TransactionMessage extends BaseMessage
{
    private static array $statuses = [
        Transaction::STATUS_SUCCEEDED => 'Succeeded',
        Transaction::STATUS_PENDING => 'Pending',
        Transaction::STATUS_FAILED => 'Failed',
    ];

    private static array $methodNames = [
        PaymentMethod::CREDIT_CARD => 'credit card',
        PaymentMethod::PAYPAL => 'PayPal',
        PaymentMethod::ACH => 'ACH',
        PaymentMethod::CASH => 'cash',
        PaymentMethod::WIRE_TRANSFER => 'wire transfer',
        PaymentMethod::CHECK => 'check',
    ];

    protected function transactionCreated(): array
    {
        $type = $this->object['type'] ?? Transaction::TYPE_PAYMENT;

        if (Transaction::TYPE_ADJUSTMENT == $type) {
            return $this->adjustmentCreated();
        } elseif (Transaction::TYPE_REFUND == $type) {
            return $this->refundCreated();
        }

        return $this->paymentCreated();
    }

    private function paymentCreated(): array
    {
        $status = $this->object['status'] ?? Transaction::STATUS_SUCCEEDED;
        $method = array_value($this->object, 'method');

        if (Transaction::STATUS_FAILED == $status) {
            $methodStr = PaymentMessage::getMethodStr($method, true);

            return [
                $this->transaction(),
                new AttributedString(' '.$methodStr.' from '),
                $this->customer('customerName'),
                new AttributedString(' failed'),
            ];
        } elseif (Transaction::STATUS_PENDING == $status) {
            $methodStr = PaymentMessage::getMethodStr($method, true);

            return [
                new AttributedString('Initiated '),
                $this->transaction(),
                new AttributedString(' '.$methodStr.' from '),
                $this->customer('customerName'),
            ];
        }

        $methodStr = PaymentMessage::getMethodStr($method);

        $result = [
            $this->customer('customerName'),
            new AttributedString(' paid '),
            $this->transaction(),
        ];

        if ('payment' !== $methodStr) {
            $result[] = new AttributedString(' via '.$methodStr);
        }

        return $result;
    }

    private function refundCreated(): array
    {
        $method = array_value($this->object, 'method');
        $methodStr = PaymentMessage::getMethodStr($method);

        return [
            $this->customer('customerName'),
            new AttributedString(' was refunded '),
            $this->transaction(),
            new AttributedString(' with '.$methodStr),
        ];
    }

    private function adjustmentCreated(): array
    {
        $amount = array_value($this->object, 'amount');
        if ($amount < 0) {
            $name = 'credit';
            $this->object['amount'] = abs($amount);
        } else {
            $name = 'adjustment';
        }

        return [
            $this->transaction(),
            new AttributedString(" $name for "),
            $this->customer('customerName'),
            new AttributedString(' was created'),
        ];
    }

    protected function transactionUpdated(): array
    {
        $type = $this->object['type'] ?? Transaction::TYPE_PAYMENT;
        $method = array_value($this->object, 'method');

        if ('adjustment' == $type) {
            return $this->adjustmentUpdated();
        }

        // determine object name
        $name = array_value(self::$methodNames, (string) $method);
        if ('refund' == $type) {
            $name = 'refund';
        } else {
            $name .= ' payment';
        }
        $name = trim($name);

        // determine update string
        $updateStr = ' was updated';

        if (isset($this->previous['status']) && isset($this->object['status'])) {
            $old = array_value(self::$statuses, $this->previous['status']);
            $new = array_value(self::$statuses, $this->object['status']);
            $updateStr = " went from \"$old\" to \"$new\"";
        } elseif (isset($this->previous['sent']) && !$this->previous['sent']) {
            $updateStr = ' was marked sent';
        }

        return [
            $this->transaction(),
            new AttributedString(' '.$name.' from '),
            $this->customer('customerName'),
            new AttributedString($updateStr),
        ];
    }

    protected function adjustmentUpdated(): array
    {
        $amount = array_value($this->object, 'amount');
        if ($amount < 0) {
            $name = 'credit';
            $this->object['amount'] = abs($amount);
        } else {
            $name = 'adjustment';
        }

        return [
            $this->transaction(),
            new AttributedString(" $name for "),
            $this->customer('customerName'),
            new AttributedString(' was updated'),
        ];
    }

    protected function transactionDeleted(): array
    {
        $type = $this->object['type'] ?? Transaction::TYPE_PAYMENT;

        if ('adjustment' == $type) {
            return $this->adjustmentDeleted();
        }

        // determine object name
        $name = 'payment';
        if ('refund' == $type) {
            $name = 'refund';
        }

        return [
            new AttributedString(ucfirst($this->an($name))." $name for "),
            $this->transaction(),
            new AttributedString(' from '),
            $this->customer('customerName'),
            new AttributedString(' was removed'),
        ];
    }

    protected function adjustmentDeleted(): array
    {
        $amount = array_value($this->object, 'amount');
        if ($amount < 0) {
            $name = 'credit';
            $this->object['amount'] = abs($amount);
        } else {
            $name = 'adjustment';
        }

        return [
            $this->transaction(),
            new AttributedString(" $name for "),
            $this->customer('customerName'),
            new AttributedString(' was removed'),
        ];
    }

    private function transaction(): AttributedObject
    {
        return new AttributedObject('transaction', $this->moneyAmount(), array_value($this->associations, 'transaction'));
    }
}
