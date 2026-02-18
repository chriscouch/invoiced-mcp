<?php

namespace App\AccountsReceivable\Search;

use App\AccountsReceivable\Models\Contact;
use App\Core\Search\Interfaces\SearchDocumentInterface;

class ContactSearchDocument implements SearchDocumentInterface
{
    public function __construct(private Contact $contact)
    {
    }

    public function toSearchDocument(): array
    {
        $customer = $this->contact->customer;

        return [
            'email' => $this->contact->email,
            'name' => $this->contact->name,
            'phone' => $this->contact->phone,
            'address1' => $this->contact->address1,
            'address2' => $this->contact->address2,
            'city' => $this->contact->city,
            'state' => $this->contact->state,
            'postal_code' => $this->contact->postal_code,
            'country' => $this->contact->country,
            '_customer' => $customer->id(),
            'customer' => [
                'name' => $customer->name,
            ],
        ];
    }
}
