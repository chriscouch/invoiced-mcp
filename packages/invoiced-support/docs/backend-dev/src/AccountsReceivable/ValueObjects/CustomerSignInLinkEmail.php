<?php

namespace App\AccountsReceivable\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Models\NetworkConnection;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Themes\Interfaces\PdfBuilderInterface;
use App\AccountsReceivable\EmailVariables\CustomerSignInEmailVariables;

readonly class CustomerSignInLinkEmail implements SendableDocumentInterface
{
    public function __construct(
        private Customer $customer,
    ) {
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new CustomerSignInEmailVariables($this->customer);
    }

    public function schemaOrgActions(): ?string
    {
        return null;
    }

    public function getSendClientUrl(): ?string
    {
        return $this->customer->sign_up_url;
    }

    public function getSendId(): int
    {
        return $this->customer->id;
    }

    public function getSendObjectType(): ?ObjectType
    {
        return null;
    }

    public function getSendCompany(): Company
    {
        return $this->customer->tenant();
    }

    public function getSendCustomer(): Customer
    {
        return $this->customer;
    }

    public function getDefaultEmailContacts(): array
    {
        return [];
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return null;
    }

    public function getThreadName(): string
    {
        return 'Sign in link for '.$this->customer->name;
    }

    public function getNetworkConnection(): ?NetworkConnection
    {
        return $this->customer->network_connection;
    }
}
