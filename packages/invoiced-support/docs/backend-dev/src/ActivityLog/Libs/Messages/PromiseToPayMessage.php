<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;
use ICanBoogie\Inflector;

class PromiseToPayMessage extends BaseMessage
{
    protected function invoicePaymentExpected(): array
    {
        $date = date('M j, Y', array_value($this->object, 'date'));
        $inflector = Inflector::get();
        $method = strtolower($inflector->humanize((string) array_value($this->object, 'method')));

        return [
            $this->customer(),
            new AttributedString(" expects payment to arrive by $date via $method for "),
            $this->invoice(),
        ];
    }

    protected function promiseToPayBroken(): array
    {
        $date = date('M j, Y', array_value($this->object, 'date'));

        return [
            $this->customer(),
            new AttributedString(' failed to pay '),
            $this->invoice(),
            new AttributedString(" by $date"),
        ];
    }
}
