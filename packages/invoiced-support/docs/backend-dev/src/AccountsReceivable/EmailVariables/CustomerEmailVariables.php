<?php

namespace App\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\Models\Customer;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Models\EmailTemplate;

/**
 * View model for customer email templates.
 */
class CustomerEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected Customer $customer)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        $billToCustomer = $this->customer->getBillToCustomer();

        $contactName = $billToCustomer->name;
        if ($attnTo = $billToCustomer->attention_to) {
            $contactName = $attnTo;
        }

        return [
            'customer_name' => $billToCustomer->name,
            'customer_contact_name' => $contactName,
            'customer_number' => $billToCustomer->number,
            'customer_address' => $billToCustomer->address,
            'customer' => [
                'metadata' => (array) $billToCustomer->metadata,
                'id' => $billToCustomer->id,
            ],
        ];
    }

    public function getCurrency(): string
    {
        return $this->customer->calculatePrimaryCurrency();
    }
}
