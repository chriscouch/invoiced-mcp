<?php

namespace App\AccountsPayable\EventSubscriber;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Enums\PayableDocumentStatus;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Operations\CreateBill;
use App\AccountsPayable\Operations\CreateVendorCredit;
use App\AccountsPayable\Operations\EditBill;
use App\AccountsPayable\Operations\EditVendorCredit;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Network\Enums\DocumentStatus;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Event\DocumentTransitionEvent;
use App\Network\Event\PostSendDocumentEvent;
use App\Network\Exception\DocumentStorageException;
use App\Network\Interfaces\DocumentStorageInterface;
use App\Network\Models\NetworkDocument;
use App\Network\Ubl\UblDocumentViewModelFactory;
use App\Network\Ubl\ViewModel\CreditNoteViewModel;
use App\Network\Ubl\ViewModel\InvoiceViewModel;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountsPayableNetworkSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TenantContext $tenant,
        private Connection $database,
        private DocumentStorageInterface $documentStorage,
        private UblDocumentViewModelFactory $documentViewModelFactory,
        private CreateBill $createBill,
        private EditBill $editBill,
        private CreateVendorCredit $createVendorCredit,
        private EditVendorCredit $editVendorCredit,
    ) {
    }

    public function sentDocument(PostSendDocumentEvent $event): void
    {
        $this->handleDocument($event->document);
    }

    public function documentTransition(DocumentTransitionEvent $event): void
    {
        // Ignore the pending approval status transition because this is handled by the send document event
        if (DocumentStatus::PendingApproval == $event->statusHistory->status) {
            return;
        }

        $this->handleDocument($event->document);
    }

    private function handleDocument(NetworkDocument $document): void
    {
        // Create the bill in the recipient's Invoiced account
        $this->tenant->runAs($document->to_company, function () use ($document) {
            if (!in_array($document->type, [NetworkDocumentType::Invoice, NetworkDocumentType::CreditNote])) {
                return;
            }

            // load the document from storage and convert to a view model
            try {
                $xml = $this->documentStorage->retrieve($document);
            } catch (DocumentStorageException) {
                // the error has already been logged
                return;
            }

            $viewModel = $this->documentViewModelFactory->make($xml);

            // create a corresponding bill or vendor credit depending on the document type
            if ($viewModel instanceof CreditNoteViewModel) {
                $this->saveVendorCredit($document, $viewModel);
            } elseif ($viewModel instanceof InvoiceViewModel) {
                $this->saveBill($document, $viewModel);
            }
        });
    }

    private function getVendorId(NetworkDocument $document): int
    {
        return (int) $this->database->fetchOne('SELECT v.id FROM Vendors v JOIN NetworkConnections c ON v.network_connection_id=c.id WHERE v.tenant_id=:tenantId AND c.vendor_id=:vendorTenantId', [
            'tenantId' => $document->to_company_id,
            'vendorTenantId' => $document->from_company_id,
        ]);
    }

    private function saveVendorCredit(NetworkDocument $document, CreditNoteViewModel $viewModel): void
    {
        $total = $viewModel->getTotal();
        $parameters = [
            'number' => $viewModel->getReference(),
            'date' => $viewModel->getIssueDate(),
            'currency' => $total?->getCurrency()->getCode(),
            'total' => $total ? Money::fromMoneyPhp($total)->toDecimal() : 0,
            'status' => $this->getPayableStatus($document->current_status),
        ];

        $vendorCredit = VendorCredit::where('network_document_id', $document)->oneOrNull();
        if ($vendorCredit) {
            $this->editVendorCredit->edit($vendorCredit, $parameters);

            return;
        }

        $vendorId = $this->getVendorId($document);
        if (!$vendorId) {
            return;
        }

        if (DocumentStatus::Voided == $document->current_status) {
            $parameters['voided'] = true;
            $parameters['date_voided'] = CarbonImmutable::now();
        }

        $parameters['network_document'] = $document;
        $parameters['vendor'] = $vendorId;
        $parameters['source'] = PayableDocumentSource::Network;
        $this->createVendorCredit->create($parameters);
    }

    private function saveBill(NetworkDocument $document, InvoiceViewModel $viewModel): void
    {
        $total = $viewModel->getTotal();
        $parameters = [
            'number' => $viewModel->getReference(),
            'date' => $viewModel->getIssueDate(),
            'due_date' => $viewModel->getDueDate(),
            'currency' => $total?->getCurrency()->getCode(),
            'total' => $total ? Money::fromMoneyPhp($total)->toDecimal() : 0,
            'status' => $this->getPayableStatus($document->current_status),
        ];

        if (DocumentStatus::Voided == $document->current_status) {
            $parameters['voided'] = true;
            $parameters['date_voided'] = CarbonImmutable::now();
        }

        $bill = Bill::where('network_document_id', $document)->oneOrNull();
        if ($bill) {
            $this->editBill->edit($bill, $parameters);

            return;
        }

        $vendorId = $this->getVendorId($document);
        if (!$vendorId) {
            return;
        }

        $parameters['network_document'] = $document;
        $parameters['vendor'] = $vendorId;
        $parameters['source'] = PayableDocumentSource::Network;
        $this->createBill->create($parameters);
    }

    private function getPayableStatus(DocumentStatus $status): PayableDocumentStatus
    {
        return match ($status) {
            DocumentStatus::PendingApproval => PayableDocumentStatus::PendingApproval,
            DocumentStatus::Approved => PayableDocumentStatus::Approved,
            DocumentStatus::Rejected => PayableDocumentStatus::Rejected,
            DocumentStatus::Paid => PayableDocumentStatus::Paid,
            DocumentStatus::Voided => PayableDocumentStatus::Voided,
        };
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DocumentTransitionEvent::class => 'documentTransition',
            PostSendDocumentEvent::class => 'sentDocument',
        ];
    }
}
