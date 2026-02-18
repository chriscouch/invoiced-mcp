<?php

namespace App\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Companies\Models\Company;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Models\NetworkConnection;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\EmailVariablesCollection;
use App\Themes\Interfaces\PdfBuilderInterface;

/**
 * This is the object that is sent for
 * chasing emails.
 */
class ChasingStatementStrategy implements PdfDocumentInterface, SendableDocumentInterface
{
    private SendableDocumentInterface $strategy;
    private ChasingStatement $chasingStatement;

    public function __construct(private readonly ChasingEvent $chasingEvent)
    {
        $this->chasingStatement = $this->strategy = new ChasingStatement($this->chasingEvent);
    }

    public function setStrategy(SendableDocumentInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getStrategy(): SendableDocumentInterface
    {
        return $this->strategy;
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        $chasingStatement = $this->chasingStatement;
        $chasingStatementVariable = $chasingStatement->getEmailVariables();
        $collection = new EmailVariablesCollection($chasingStatement->getSendCustomer(), $chasingStatementVariable->getCurrency());
        $collection->addVariables($chasingStatementVariable);

        return $collection;
    }

    public function schemaOrgActions(): ?string
    {
        return $this->chasingStatement->schemaOrgActions();
    }

    public function getSendClientUrl(): ?string
    {
        return $this->chasingStatement->getSendClientUrl();
    }

    public function getSendId(): int
    {
        return $this->chasingStatement->getSendId();
    }

    public function getSendObjectType(): ?ObjectType
    {
        return $this->chasingStatement->getSendObjectType();
    }

    public function getSendCompany(): Company
    {
        return $this->chasingStatement->getSendCompany();
    }

    public function getSendCustomer(): Customer
    {
        return $this->chasingStatement->getSendCustomer();
    }

    public function getDefaultEmailContacts(): array
    {
        return $this->chasingStatement->getDefaultEmailContacts();
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        return $this->strategy->getPdfBuilder();
    }

    public function getThreadName(): string
    {
        return $this->chasingStatement->getThreadName();
    }

    public function getNetworkConnection(): ?NetworkConnection
    {
        return $this->chasingStatement->getNetworkConnection();
    }
}
