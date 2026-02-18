<?php

namespace App\Sending\Email\Libs;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Database\TransactionManager;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Libs\AbstractSendChannel;
use App\Sending\Models\ScheduledSend;
use App\Sending\ValueObjects\ScheduledSendParameters;
use Carbon\CarbonImmutable;

class EmailSendChannel extends AbstractSendChannel
{
    public function __construct(
        private DocumentEmailFactory $factory,
        private EmailSender $sender,
        private TransactionManager $transactionManager
    ) {
    }

    public function send(ScheduledSend $scheduledSend): void
    {
        // get document to send
        $document = $scheduledSend->getDocument();
        if (!($document instanceof ReceivableDocument)) {
            $scheduledSend->markFailed('No document was configured to be sent.')
                ->saveOrFail();

            return;
        }

        // build recipients
        $invoiceDelivery = $this->getInvoiceDelivery($scheduledSend);
        $parameters = $scheduledSend->getParameters();
        $to = $this->buildTo($parameters, $document, $invoiceDelivery);
        $cc = $parameters->getCc() ?? [];
        $bcc = $this->buildBcc($parameters);
        if (0 == count($to)) {
            $scheduledSend->markFailed('Contact information is missing.')
                ->saveOrFail();

            return;
        }

        // get template
        $template = (new DocumentEmailTemplateFactory())->get($document);

        try {
            $email = $this->factory->make($document, $template, $to, $cc, $bcc, $parameters->getSubject(), $parameters->getMessage());
            $this->sender->send($email);
            $scheduledSend->markSent();
        } catch (SendEmailException $e) {
            $scheduledSend->markFailed($e->getMessage());
        }

        // update delivery last sent time
        $this->transactionManager->perform(function () use ($scheduledSend, $invoiceDelivery) {
            $scheduledSend->saveOrFail();
            if ($invoiceDelivery) {
                $invoiceDelivery->setLastSentEmail(CarbonImmutable::now());
                $invoiceDelivery->saveOrFail();
            }
        });
    }

    /**
     * Builds 'to' contacts.
     *
     * NOTE: If $scheduledSend->to is null, the contacts returned
     * will be the default email contacts for the invoice.
     */
    public function buildTo(ScheduledSendParameters $parameters, ReceivableDocument $document, ?InvoiceDelivery $delivery): array
    {
        $contacts = [];

        $role = $parameters->getRole();
        if ($role) {
            $roleContacts = Contact::where('customer_id', $document->customer())
                ->where('role_id', $role)
                ->first(100);
            foreach ($roleContacts as $roleContact) {
                if ($email = $roleContact->getEmail()) {
                    $contacts[] = [
                        'name' => $roleContact->name,
                        'email' => $email,
                    ];
                }
            }
        } elseif ($to = $parameters->getTo()) {
            $contacts = $to;
        } else {
            $contacts = $document->getDefaultEmailContacts();
        }

        // include `send_new_invoices` contacts
        if ($delivery && !$delivery->last_sent_email) {
            foreach ($delivery->invoice->customer()->contacts(false) as $contact) {
                if (!$contact->send_new_invoices || !$contact->email) {
                    continue;
                }

                $contacts[] = [
                    'name' => $contact->name,
                    'email' => $contact->email,
                ];
            }
        }

        return array_unique($contacts, SORT_REGULAR);
    }

    /**
     * Builds 'bcc' for DocumentEmailFactory::make.
     */
    public function buildBcc(ScheduledSendParameters $parameters): ?string
    {
        $bcc = $parameters->getBcc();
        if (!is_array($bcc)) {
            return null;
        }

        $emails = [];
        foreach ($bcc as $recipient) {
            $emails[] = $recipient['email'];
        }

        return implode(',', $emails);
    }
}
