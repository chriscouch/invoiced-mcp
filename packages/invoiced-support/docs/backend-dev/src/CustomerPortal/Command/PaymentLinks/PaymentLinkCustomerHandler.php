<?php

namespace App\CustomerPortal\Command\PaymentLinks;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\Enums\ObjectType;
use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\CustomerPortal\ValueObjects\PaymentLinkResult;
use stdClass;

class PaymentLinkCustomerHandler
{
    /**
     * Creates or retrieves a customer for a payment link submission.
     *
     * @throws ModelException|PaymentLinkException
     */
    public function handle(PaymentLinkResult $result, array $parameters): void
    {
        $customer = $result->paymentLink->customer;

        // If there is no customer then look for one based on the provided customer number.
        if (!$customer && isset($parameters['client_id']) && $parameters['client_id']) {
            $customer = Customer::where('number', $parameters['client_id'])->oneOrNull();
        }

        // When there is still no customer one must be created.
        if ($customer) {
            $this->updateCustomer($result->paymentLink, $customer, $parameters);
        } else {
            $customer = $this->createCustomer($result->paymentLink, $parameters);
        }

        $result->setCustomer($customer);
        $this->updatePaymentFlow($result);
    }

    /**
     * Creates a customer for a payment link submission.
     *
     * @throws ModelException|PaymentLinkException
     */
    private function createCustomer(PaymentLink $paymentLink, array $parameters): Customer
    {
        $customer = new Customer();
        $customer->number = (string) ($parameters['client_id'] ?? '');
        $customer->currency = $paymentLink->currency;
        $customer->email = $parameters['email'] ?? null;
        $customer->phone = $parameters['phone'] ?? null;

        // Name
        $individualName = trim(($parameters['first_name'] ?? '').' '.($parameters['last_name'] ?? ''));
        $companyName = trim($parameters['company'] ?? '');
        if ($companyName) {
            $customer->name = $companyName;
            $customer->type = 'company';
            $customer->attention_to = $individualName;
        } elseif ($individualName) {
            $customer->name = $individualName;
            $customer->type = 'person';
        }
        $customer->name = $customer->name ?: $customer->number;

        // Address
        $address = $parameters['address'] ?? [];
        $customer->address1 = $address['address1'] ?? null;
        $customer->address2 = $address['address2'] ?? null;
        $customer->city = $address['city'] ?? null;
        $customer->state = $address['state'] ?? null;
        $customer->postal_code = $address['postal_code'] ?? null;
        $customer->country = $address['country'] ?? '';

        // Custom Fields
        $this->setFields($paymentLink, $customer, new stdClass(), $parameters);

        $customer->saveOrFail();

        return $customer;
    }

    /**
     * Updates a customer for a payment link submission.
     *
     * @throws ModelException|PaymentLinkException
     */
    private function updateCustomer(PaymentLink $paymentLink, Customer $customer, array $parameters): void
    {
        $changed = false;
        if (isset($parameters['email'])) {
            $changed = true;
            $customer->email = $parameters['email'];
        }

        if (isset($parameters['phone'])) {
            $changed = true;
            $customer->phone = $parameters['phone'];
        }

        // Address
        if (isset($parameters['address'])) {
            $changed = true;
            $address = $parameters['address'];
            $customer->address1 = $address['address1'] ?? null;
            $customer->address2 = $address['address2'] ?? null;
            $customer->city = $address['city'] ?? null;
            $customer->state = $address['state'] ?? null;
            $customer->postal_code = $address['postal_code'] ?? null;
            $customer->country = $address['country'] ?? '';
        }

        // Custom Fields
        $changed = $changed || $this->setFields($paymentLink, $customer, $customer->metadata, $parameters);

        if ($changed) {
            $customer->saveOrFail();
        }
    }

    /**
     * @throws PaymentLinkException
     */
    private function setFields(PaymentLink $paymentLink, Customer $customer, object $metadata, array $parameters): bool
    {
        $fields = PaymentLinkField::getForObjectType($paymentLink, ObjectType::Customer);
        $changed = false;
        foreach ($fields as $field) {
            $formId = $field->getFormId();
            $value = $parameters[$formId] ?? null;
            if ($value) {
                $changed = true;
                $metadata->{$field->custom_field_id} = $value;
            } elseif ($field->required) {
                throw new PaymentLinkException('Missing required customer field "'.$field->custom_field_id.'"');
            }
        }

        if ($changed) {
            $customer->metadata = $metadata;
        }

        return $changed;
    }

    private function updatePaymentFlow(PaymentLinkResult $result): void
    {
        $paymentFlow = $result->getPaymentFlow();
        if (!$paymentFlow->customer) {
            $paymentFlow->customer = $result->getCustomer();
            $paymentFlow->saveOrFail();
        }
    }
}
