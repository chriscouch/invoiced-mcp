<?php

namespace App\Sending\Email\Interfaces;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Models\NetworkConnection;
use App\Themes\Interfaces\PdfBuilderInterface;

/**
 * Customer-facing objects that can be sent via email,
 * like invoices, must implement this interface.
 */
interface SendableDocumentInterface
{
    /**
     * Gets the class that can generate the email variables
     * for this model.
     */
    public function getEmailVariables(): EmailVariablesInterface;

    /**
     * Generates schema.org HTML actions.
     */
    public function schemaOrgActions(): ?string;

    /**
     * Gets the client view URL, if any, of this document.
     */
    public function getSendClientUrl(): ?string;

    /**
     * Gets the object ID.
     */
    public function getSendId(): int;

    /**
     * Gets the object type.
     */
    public function getSendObjectType(): ?ObjectType;

    /**
     * Gets the company.
     */
    public function getSendCompany(): Company;

    /**
     * Gets the customer.
     */
    public function getSendCustomer(): Customer;

    /**
     * Gets the default email contacts when
     * sending this object.
     */
    public function getDefaultEmailContacts(): array;

    /**
     * Gets the PDF builder for this object.
     */
    public function getPdfBuilder(): ?PdfBuilderInterface;

    /**
     * Gets the document email thread name.
     */
    public function getThreadName(): string;

    /**
     * Gets the network connection that is associated with this document.
     */
    public function getNetworkConnection(): ?NetworkConnection;
}
