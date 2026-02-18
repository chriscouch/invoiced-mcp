<?php

namespace App\Network\Command;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Utils\Enums\ObjectType;
use App\Network\Enums\DocumentFormat;
use App\Network\Enums\DocumentStatus;
use App\Network\Event\PostSendDocumentEvent;
use App\Network\Event\PostSendModelEvent;
use App\Network\Exception\DocumentStorageException;
use App\Network\Exception\NetworkSendException;
use App\Network\Exception\UblValidationException;
use App\Network\Interfaces\DocumentStorageInterface;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentVersion;
use App\Network\Models\NetworkQueuedSend;
use App\Network\Ubl\ModelUblTransformer;
use App\Network\Ubl\UblDocumentValidator;
use App\Network\Ubl\UblDocumentViewModelFactory;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use App\Core\Orm\Model;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SendDocument
{
    private const MAX_SIZE_MB = 20;

    public function __construct(
        private UblDocumentValidator $validator,
        private DocumentStorageInterface $storage,
        private TransitionDocumentStatus $transitionDocumentStatus,
        private EventDispatcherInterface $dispatcher,
        private ModelUblTransformer $modelTransformer,
    ) {
    }

    /**
     * This queues a document to be sent later once a
     * network connection has been formed. This method
     * should only be used to send a document that is not.
     *
     * @throws NetworkSendException
     */
    public function queueToSend(?Member $user, Customer $customer, object $document): NetworkQueuedSend
    {
        if (!$document instanceof Model) {
            throw new NetworkSendException('This type of document cannot be sent later. The recipient needs to join your business network first.');
        }

        if ($customer->network_connection) {
            throw new NetworkSendException('Documents cannot be sent later if there is already a network connection in place.');
        }

        $typeId = ObjectType::fromModel($document)->value;
        $documentId = (int) $document->id();

        // look for existing queued send
        $existing = NetworkQueuedSend::where('object_type', $typeId)
            ->where('object_id', $documentId)
            ->oneOrNull();
        if ($existing instanceof NetworkQueuedSend) {
            return $existing;
        }

        $queuedSend = new NetworkQueuedSend();
        $queuedSend->object_type = $typeId;
        $queuedSend->object_id = $documentId;
        $queuedSend->customer = $customer;
        $queuedSend->member = $user;
        $queuedSend->saveOrFail();

        return $queuedSend;
    }

    /**
     * Sends a document to a network connection using an Invoiced model.
     * This method will first convert the model to UBL before sending.
     *
     * @throws NetworkSendException
     */
    public function sendFromModel(Company $from, ?Member $user, NetworkConnection $connection, object $document): NetworkDocument
    {
        try {
            $data = $this->modelTransformer->transform($document);
        } catch (UblValidationException $e) {
            throw new NetworkSendException($e->getMessage());
        }

        $networkDocument = $this->sendFromXml($from, $user, $connection, $data);

        // emit an event for other listeners to add behavior
        $this->dispatcher->dispatch(new PostSendModelEvent($document, $networkDocument));

        return $networkDocument;
    }

    /**
     * Sends a document to a network connection that is
     * already converted to UBL.
     *
     * @throws NetworkSendException
     */
    public function sendFromXml(Company $from, ?Member $user, NetworkConnection $connection, string $data): NetworkDocument
    {
        $to = $connection->getCounterparty($from);

        if ($to->canceled) {
            throw new NetworkSendException('The organization you are trying to send to is disabled');
        }

        $this->validate($data);

        // convert to a view model to extract data from document
        $factory = new UblDocumentViewModelFactory();
        $viewModel = $factory->make($data);
        $documentType = $viewModel->getType();
        $reference = $viewModel->getReference();

        // check for an existing document
        $document = NetworkDocument::where('from_company_id', $from)
            ->where('to_company_id', $to)
            ->where('type', $documentType->value)
            ->where('reference', $reference)
            ->oneOrNull();

        if ($document instanceof NetworkDocument) {
            $maxVersions = $from->quota->get(QuotaType::MaxDocumentVersions);
            if ($document->version >= $maxVersions) {
                throw new NetworkSendException('The same document cannot be sent more than '.$maxVersions.' times');
            }

            // check if transition is allowed for current document status
            // e.g. cannot resend if voided
            if (!$this->transitionDocumentStatus->isTransitionAllowed($document->current_status, DocumentStatus::PendingApproval, true)) {
                throw new NetworkSendException('You are not allowed to resend this document because it currently has a status of: '.$document->current_status->name);
            }

            $previous = clone $document;

            // increment the version by 1
            $document->version = $document->version + 1;
        } else {
            // create a new document
            $document = new NetworkDocument();
            $document->from_company = $from;
            $document->to_company = $to;
            $document->format = DocumentFormat::UniversalBusinessLanguage;
            $document->version = 1;
            $document->type = $documentType;
            $document->reference = $reference;
            $previous = null;
        }

        if ($total = $viewModel->getSummaryTotal()) {
            $document->currency = $total->getCurrency()->getCode();
            $moneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
            $document->total = (float) $moneyFormatter->format($total);
        }

        // transition document to pending approval
        $this->transitionDocumentStatus->performTransition($document, $from, DocumentStatus::PendingApproval, $user);

        $document->saveOrFail();

        // save to storage
        try {
            $this->storage->persist($document, $data);
        } catch (DocumentStorageException) {
            throw new NetworkSendException('There was an error sending your document');
        }

        // store the metadata in the database
        $documentData = new NetworkDocumentVersion();
        $documentData->document = $document;
        $documentData->size = strlen($data);
        $documentData->version = $document->version;
        $documentData->saveOrFail();

        // emit an event for other listeners to add behavior
        $this->dispatcher->dispatch(new PostSendDocumentEvent($document, $previous));

        return $document;
    }

    /**
     * @throws NetworkSendException
     */
    private function validate(string $data): void
    {
        // validate size
        $max = self::MAX_SIZE_MB * 1024 * 1024;
        if (strlen($data) > $max) {
            throw new NetworkSendException('The document exceeds the maximum size limit of '.self::MAX_SIZE_MB.'MB');
        }

        // validate the document before sending
        try {
            $this->validator->validate($data);
        } catch (UblValidationException $e) {
            throw new NetworkSendException($e->getMessage());
        }
    }
}
