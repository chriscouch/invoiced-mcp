<?php

namespace App\Sending\Email\Traits;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Models\NetworkConnection;

/**
 * Common methods for objects that implement SendableDocumentInterface.
 */
trait SendableDocumentTrait
{
    /**
     * Gets the object ID.
     */
    public function getSendId(): int
    {
        return (int) $this->id();
    }

    public function getSendObjectType(): ?ObjectType
    {
        return ObjectType::fromModel($this);
    }

    /**
     * Gets the company object.
     */
    public function getSendCompany(): Company
    {
        return $this->tenant();
    }

    /**
     * Gets the customer object.
     */
    public function getSendCustomer(): Customer
    {
        return $this->customer();
    }

    /**
     * Gets the default email contacts when
     * sending this object.
     */
    public function getDefaultEmailContacts(): array
    {
        return $this->getSendCustomer()->emailContacts();
    }

    public function getThreadName(): string
    {
        return $this->getSendCustomer()->number;
    }

    public function getNetworkConnection(): ?NetworkConnection
    {
        return $this->getSendCustomer()->network_connection;
    }
}
