<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\Enums\ObjectType;

class CreatePaymentLink
{
    public function __construct(private TenantContext $tenant)
    {
    }

    /**
     * @throws ModelException
     */
    public function create(array $parameters): PaymentLink
    {
        $items = $parameters['items'] ?? [];
        unset($parameters['items']);
        $fields = $parameters['fields'] ?? [];
        unset($parameters['fields']);

        if (isset($parameters['customer']) && !$parameters['customer'] instanceof Customer) {
            $parameters['customer'] = Customer::findOrFail($parameters['customer']);
        }

        $paymentLink = new PaymentLink();
        foreach ($parameters as $k => $value) {
            $paymentLink->$k = $value;
        }
        if (!$paymentLink->currency) {
            $paymentLink->currency = $this->tenant->get()->currency;
        }
        $paymentLink->status = PaymentLinkStatus::Active;
        $paymentLink->saveOrFail();

        $this->saveItems($paymentLink, $items);
        $this->saveFields($paymentLink, $fields);

        return $paymentLink;
    }

    private function saveItems(PaymentLink $paymentLink, array $items): void
    {
        foreach ($items as $values) {
            $item = new PaymentLinkItem();
            foreach ($values as $k => $value) {
                $item->$k = $value;
            }
            $item->payment_link = $paymentLink;
            $item->saveOrFail();
        }
    }

    private function saveFields(PaymentLink $paymentLink, array $fields): void
    {
        $order = 1;
        foreach ($fields as $values) {
            $field = new PaymentLinkField();
            if (isset($values['object_type'])) {
                $values['object_type'] = ObjectType::fromTypeName($values['object_type']);
            }
            foreach ($values as $k => $value) {
                $field->$k = $value;
            }
            $field->order = $order;
            ++$order;
            $field->payment_link = $paymentLink;
            $field->saveOrFail();
        }
    }
}
