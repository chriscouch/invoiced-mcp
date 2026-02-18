<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\Enums\ObjectType;

class EditPaymentLink
{
    /**
     * @throws ModelException
     */
    public function edit(PaymentLink $paymentLink, array $parameters): PaymentLink
    {
        if (PaymentLinkStatus::Active != $paymentLink->status) {
            throw new InvalidRequest('Unable to edit a payment link that is not active');
        }

        $items = $parameters['items'] ?? null;
        unset($parameters['items']);
        $fields = $parameters['fields'] ?? null;
        unset($parameters['fields']);

        if (isset($parameters['customer']) && !$parameters['customer'] instanceof Customer) {
            $parameters['customer'] = Customer::findOrFail($parameters['customer']);
        }

        foreach ($parameters as $k => $value) {
            $paymentLink->$k = $value;
        }

        // Determine new status, active or complete
        // Turning off reusable should make status complete if already completed
        $paymentLink->status = PaymentLinkStatus::Active;
        if (!$paymentLink->reusable) {
            $count = PaymentLinkSession::where('payment_link_id', $paymentLink)
                ->where('completed_at', null, '<>')
                ->count();
            if ($count > 0) {
                $paymentLink->status = PaymentLinkStatus::Completed;
            }
        }

        $paymentLink->saveOrFail();

        $this->saveItems($paymentLink, $items);
        $this->saveFields($paymentLink, $fields);

        return $paymentLink;
    }

    private function saveItems(PaymentLink $paymentLink, ?array $items): void
    {
        if (!is_array($items)) {
            return;
        }

        $idsToKeep = [];
        foreach ($items as $values) {
            if (isset($values['id'])) {
                $item = PaymentLinkItem::where('id', $values['id'])
                    ->where('payment_link_id', $paymentLink)
                    ->one();
                unset($values['id']);
            } else {
                $item = new PaymentLinkItem();
                $item->payment_link = $paymentLink;
            }

            foreach ($values as $k => $value) {
                $item->$k = $value;
            }
            $item->saveOrFail();
            $idsToKeep[] = $item->id();
        }

        // remove deleted items
        $query = PaymentLinkItem::where('payment_link_id', $paymentLink);
        if ($idsToKeep) {
            $query->where('id NOT IN ('.implode(',', $idsToKeep).')');
        }
        $query->delete();
    }

    private function saveFields(PaymentLink $paymentLink, ?array $fields): void
    {
        if (!is_array($fields)) {
            return;
        }

        $idsToKeep = [];
        $order = 1;
        foreach ($fields as $values) {
            if (isset($values['id'])) {
                $field = PaymentLinkField::where('id', $values['id'])
                    ->where('payment_link_id', $paymentLink)
                    ->one();
                unset($values['id']);
            } else {
                $field = new PaymentLinkField();
                $field->payment_link = $paymentLink;
            }

            if (isset($values['object_type'])) {
                $values['object_type'] = ObjectType::fromTypeName($values['object_type']);
            }

            foreach ($values as $k => $value) {
                $field->$k = $value;
            }
            $field->order = $order;
            ++$order;
            $field->saveOrFail();
            $idsToKeep[] = $field->id();
        }

        // remove deleted fields
        $query = PaymentLinkField::where('payment_link_id', $paymentLink);
        if ($idsToKeep) {
            $query->where('id NOT IN ('.implode(',', $idsToKeep).')');
        }
        $query->delete();
    }
}
