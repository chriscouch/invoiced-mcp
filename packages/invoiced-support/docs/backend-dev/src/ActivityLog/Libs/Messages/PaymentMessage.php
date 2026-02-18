<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\PaymentProcessing\Models\PaymentMethod;

class PaymentMessage extends BaseMessage
{
    public static array $methodNames = [
        PaymentMethod::CREDIT_CARD => 'credit card',
        PaymentMethod::PAYPAL => 'PayPal',
        PaymentMethod::ACH => 'ACH',
        PaymentMethod::CASH => 'cash',
        PaymentMethod::WIRE_TRANSFER => 'wire transfer',
        PaymentMethod::CHECK => 'check',
    ];

    protected function paymentCreated(): array
    {
        $method = array_value($this->object, 'method');
        $methodStr = PaymentMessage::getMethodStr($method);

        if (isset($this->object['customer'])) {
            $result = [
                $this->customer('customerName'),
                new AttributedString(' paid '),
                $this->payment(),
            ];

            if ('payment' !== $methodStr) {
                $result[] = new AttributedString(' via '.$methodStr);
            }

            return $result;
        }

        return [
            $this->payment(),
            new AttributedString(" $methodStr was received"),
        ];
    }

    protected function paymentUpdated(): array
    {
        $method = array_value($this->object, 'method');
        $methodStr = PaymentMessage::getMethodStr($method);

        $result = [
            $this->payment(),
            new AttributedString(" $methodStr "),
        ];

        if (isset($this->object['customer'])) {
            $result[] = new AttributedString(' from ');
            $result[] = $this->customer('customerName');
        }

        // determine update string
        $updateStr = ' was updated';

        // matched
        if (array_key_exists('matched', $this->previous)) {
            if ($this->object['matched']) {
                $updateStr = ' has available matches from CashMatch AI';
            } else {
                $updateStr = ' has no available matches from CashMatch AI';
            }
        }

        $result[] = new AttributedString($updateStr);

        return $result;
    }

    protected function paymentDeleted(): array
    {
        $result = [
            new AttributedString('A payment for '),
            $this->payment(),
        ];

        if (isset($this->object['customer'])) {
            $result[] = new AttributedString(' from ');
            $result[] = $this->customer('customerName');
        }

        $result[] = new AttributedString(' was voided');

        return $result;
    }

    public static function getMethodStr(?string $method, bool $prefix = false): string
    {
        $methodStr = array_value(self::$methodNames, $method ?? '');
        if (!$methodStr) {
            $methodStr = 'payment';
        }

        if ($prefix && 'payment' !== $methodStr) {
            $methodStr .= ' payment';
        }

        return $methodStr;
    }

    private function payment(): AttributedObject
    {
        return new AttributedObject('payment', $this->moneyAmount(), array_value($this->associations, 'payment'));
    }
}
