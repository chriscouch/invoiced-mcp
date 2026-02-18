<?php

namespace App\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\EmailVariables\ChasingStatementEmailVariables;
use App\Chasing\Pdf\ChasingPdf;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Companies\Models\Company;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Traits\SendableDocumentTrait;
use App\Themes\Interfaces\PdfBuilderInterface;

/**
 * This is the object that is sent for
 * chasing emails.
 */
class ChasingStatement implements PdfDocumentInterface, SendableDocumentInterface
{
    use SendableDocumentTrait;

    //
    // SendableDocumentInterface
    //

    public function __construct(private ChasingEvent $chasingEvent)
    {
    }

    public function getSendId(): int
    {
        return (int) $this->chasingEvent->getCustomer()->id();
    }

    public function getSendObjectType(): ?ObjectType
    {
        return null;
    }

    public function getSendCompany(): Company
    {
        return $this->chasingEvent->getCustomer()->tenant();
    }

    public function getSendCustomer(): Customer
    {
        return $this->chasingEvent->getCustomer();
    }

    public function getDefaultEmailContacts(): array
    {
        return $this->getSendCustomer()->emailContacts();
    }

    public function getEmailVariables(): EmailVariablesInterface
    {
        return new ChasingStatementEmailVariables($this->chasingEvent);
    }

    public function schemaOrgActions(): ?string
    {
        $buttonText = 'View Statement';
        $url = $this->chasingEvent->getClientUrl();
        $description = 'Please pay your balance';

        return EmailHtml::schemaOrgViewAction($buttonText, $url, $description);
    }

    public function getSendClientUrl(): ?string
    {
        return $this->chasingEvent->getClientUrl();
    }

    public function getPdfBuilder(): ?PdfBuilderInterface
    {
        $invoices = $this->chasingEvent->getInvoices();
        if (0 == count($invoices)) {
            return null;
        }

        return new ChasingPdf($this->chasingEvent);
    }

    //
    // Getters
    //

    public function getChasingEvent(): ChasingEvent
    {
        return $this->chasingEvent;
    }

    public function getThreadName(): string
    {
        return $this->getChasingEvent()->getCustomer()->number;
    }
}
